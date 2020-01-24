<?php

namespace Drupal\update;

/**
 * Utility class to calculate project update status.
 *
 * @internal
 */
final class UpdateProjectStatus {

  /**
   * @var array
   */
  protected $projectData;

  /**
   * @var array
   */
  protected $availableReleases;


  /**
   * UpdateProjectStatus constructor.
   * @param array $project_data
   *   An array containing information about a specific project.
   * @param array $available_releases
   *   Data about available project releases of a specific project.
   */
  public function __construct(array $project_data, array $available_releases) {
    $this->projectData = $project_data;
    $this->availableReleases = $available_releases;
  }

  /**
   * Calculates the current update status of a specific project.
   *
   * This function is the heart of the update status feature. For each project it
   * is invoked with, it first checks if the project has been flagged with a
   * special status like "unsupported" or "insecure", or if the project node
   * itself has been unpublished. In any of those cases, the project is marked
   * with an error and the next project is considered.
   *
   * If the project itself is valid, the function decides what major release
   * series to consider. The project defines its currently supported branches in
   * its Drupal.org for the project, so the first step is to make sure the
   * development branch of the current version is still supported. If so, then the
   * major version of the current version is used. If the current version is not
   * in a supported branch, the next supported branch is used to determine the
   * major version to use. There's also a check to make sure that this function
   * never recommends an earlier release than the currently installed major
   * version.
   *
   * Given a target major version, the available releases are scanned looking for
   * the specific release to recommend (avoiding beta releases and development
   * snapshots if possible). For the target major version, the highest patch level
   * is found. If there is a release at that patch level with no extra ("beta",
   * etc.), then the release at that patch level with the most recent release date
   * is recommended. If every release at that patch level has extra (only betas),
   * then the latest release from the previous patch level is recommended. For
   * example:
   *
   * - 1.6-bugfix <-- recommended version because 1.6 already exists.
   * - 1.6
   *
   * or
   *
   * - 1.6-beta
   * - 1.5 <-- recommended version because no 1.6 exists.
   * - 1.4
   *
   * Also, the latest release from the same major version is looked for, even beta
   * releases, to display to the user as the "Latest version" option.
   * Additionally, the latest official release from any higher major versions that
   * have been released is searched for to provide a set of "Also available"
   * options.
   *
   * Finally, and most importantly, the release history continues to be scanned
   * until the currently installed release is reached, searching for anything
   * marked as a security update. If any security updates have been found between
   * the recommended release and the installed version, all of the releases that
   * included a security fix are recorded so that the site administrator can be
   * warned their site is insecure, and links pointing to the release notes for
   * each security update can be included (which, in turn, will link to the
   * official security announcements for each vulnerability).
   *
   * This function relies on the fact that the .xml release history data comes
   * sorted based on major version and patch level, then finally by release date
   * if there are multiple releases such as betas from the same major.patch
   * version (e.g., 5.x-1.5-beta1, 5.x-1.5-beta2, and 5.x-1.5). Development
   * snapshots for a given major version are always listed last.
   *
   * @return array
   *   The updated project data.
   */
  public function getUpdateProjectData() {
    $this->doCalculateProjectStatus();
    return $this->projectData;
  }

  /**
   * Calculates the project status.
   *
   * For a detailed description of how the status is calculated see
   * ::getUpdateProjectData().
   */
  private function doCalculateProjectStatus() {
    foreach (['title', 'link'] as $attribute) {
      if (!isset($this->projectData[$attribute]) && isset($this->availableReleases[$attribute])) {
        $this->projectData[$attribute] = $this->availableReleases[$attribute];
      }
    }

    // If the project status is marked as something bad, there's nothing else
    // to consider.
    if (isset($this->availableReleases['project_status'])) {
      switch ($this->availableReleases['project_status']) {
        case 'insecure':
          $this->projectData['status'] = UpdateManagerInterface::NOT_SECURE;
          if (empty($this->projectData['extra'])) {
            $this->projectData['extra'] = [];
          }
          $this->projectData['extra'][] = [
            'label' => t('Project not secure'),
            'data' => t('This project has been labeled insecure by the Drupal security team, and is no longer available for download. Immediately disabling everything included by this project is strongly recommended!'),
          ];
          break;
        case 'unpublished':
        case 'revoked':
          $this->projectData['status'] = UpdateManagerInterface::REVOKED;
          if (empty($this->projectData['extra'])) {
            $this->projectData['extra'] = [];
          }
          $this->projectData['extra'][] = [
            'label' => t('Project revoked'),
            'data' => t('This project has been revoked, and is no longer available for download. Disabling everything included by this project is strongly recommended!'),
          ];
          break;
        case 'unsupported':
          $this->projectData['status'] = UpdateManagerInterface::NOT_SUPPORTED;
          if (empty($this->projectData['extra'])) {
            $this->projectData['extra'] = [];
          }
          $this->projectData['extra'][] = [
            'label' => t('Project not supported'),
            'data' => t('This project is no longer supported, and is no longer available for download. Disabling everything included by this project is strongly recommended!'),
          ];
          break;
        case 'not-fetched':
          $this->projectData['status'] = UpdateFetcherInterface::NOT_FETCHED;
          $this->projectData['reason'] = t('Failed to get available update data.');
          break;

        default:
          // Assume anything else (e.g. 'published') is valid and we should
          // perform the rest of the logic in this function.
          break;
      }
    }

    if (!empty($this->projectData['status'])) {
      // We already know the status for this project, so there's nothing else to
      // compute. Record the project status into  $this->projectData and we're done.
      $this->projectData['project_status'] = $this->availableReleases['project_status'];
      return;
    }

    // Figure out the target major version.
    try {
      $existing_major = ModuleVersion::createFromVersionString($this->projectData['existing_version'])
        ->getMajorVersion();
    } catch (UnexpectedValueException $exception) {
      // If the version has an unexpected value we can't determine updates.
      return;
    }
    $supported_branches = [];
    if (isset($this->availableReleases['supported_branches'])) {
      $supported_branches = explode(',', $this->availableReleases['supported_branches']);
    }

    $is_in_supported_branch = function ($version) use ($supported_branches) {
      foreach ($supported_branches as $supported_branch) {
        if (strpos($version, $supported_branch) === 0) {
          return TRUE;
        }
      }
      return FALSE;
    };
    if ($is_in_supported_branch($this->projectData['existing_version'])) {
      // Still supported, stay at the current major version.
      $target_major = $existing_major;
    }
    elseif ($supported_branches) {
      // We know the current release is unsupported since it is not in
      // 'supported_branches' list. We should use the next valid supported
      // branch for the target major version.
      $this->projectData['status'] = UpdateManagerInterface::NOT_SUPPORTED;
      foreach ($supported_branches as $supported_branch) {
        try {
          $target_major = ModuleVersion::createFromSupportBranch($supported_branch)
            ->getMajorVersion();

        } catch (UnexpectedValueException $exception) {
          continue;
        }
      }
      if (!isset($target_major)) {
        // If there are no valid support branches, use the current major.
        $target_major = $existing_major;
      }

    }
    else {
      // Malformed XML file? Stick with the current branch.
      $target_major = $existing_major;
    }

    // Make sure we never tell the admin to downgrade. If we recommended an
    // earlier version than the one they're running, they'd face an
    // impossible data migration problem, since Drupal never supports a DB
    // downgrade path. In the unfortunate case that what they're running is
    // unsupported, and there's nothing newer for them to upgrade to, we
    // can't print out a "Recommended version", but just have to tell them
    // what they have is unsupported and let them figure it out.
    $target_major = max($existing_major, $target_major);

    // If the project is marked as UpdateFetcherInterface::FETCH_PENDING, it
    // means that the data we currently have (if any) is stale, and we've got a
    // task queued up to (re)fetch the data. In that case, we mark it as such,
    // merge in whatever data we have (e.g. project title and link), and move on.
    if (!empty($this->availableReleases['fetch_status']) && $this->availableReleases['fetch_status'] == UpdateFetcherInterface::FETCH_PENDING) {
      $this->projectData['status'] = UpdateFetcherInterface::FETCH_PENDING;
      $this->projectData['reason'] = t('No available update data');
      $this->projectData['fetch_status'] = $this->availableReleases['fetch_status'];
      return;
    }

    // Defend ourselves from XML history files that contain no releases.
    if (empty($this->availableReleases['releases'])) {
      $this->projectData['status'] = UpdateFetcherInterface::UNKNOWN;
      $this->projectData['reason'] = t('No available releases found');
      return;
    }

    $recommended_version_without_extra = '';
    $recommended_release = NULL;

    foreach ($this->availableReleases['releases'] as $version => $release) {
      try {
        $release_module_version = ModuleVersion::createFromVersionString($release['version']);
      } catch (UnexpectedValueException $exception) {
        continue;
      }
      // First, if this is the existing release, check a few conditions.
      if ($this->projectData['existing_version'] === $version) {
        if (isset($release['terms']['Release type']) &&
          in_array('Insecure', $release['terms']['Release type'])) {
          $this->projectData['status'] = UpdateManagerInterface::NOT_SECURE;
        }
        elseif ($release['status'] == 'unpublished') {
          $this->projectData['status'] = UpdateManagerInterface::REVOKED;
          if (empty($this->projectData['extra'])) {
            $this->projectData['extra'] = [];
          }
          $this->projectData['extra'][] = [
            'class' => ['release-revoked'],
            'label' => t('Release revoked'),
            'data' => t('Your currently installed release has been revoked, and is no longer available for download. Disabling everything included in this release or upgrading is strongly recommended!'),
          ];
        }
        elseif (isset($release['terms']['Release type']) &&
          in_array('Unsupported', $release['terms']['Release type'])) {
          $this->projectData['status'] = UpdateManagerInterface::NOT_SUPPORTED;
          if (empty($this->projectData['extra'])) {
            $this->projectData['extra'] = [];
          }
          $this->projectData['extra'][] = [
            'class' => ['release-not-supported'],
            'label' => t('Release not supported'),
            'data' => t('Your currently installed release is now unsupported, and is no longer available for download. Disabling everything included in this release or upgrading is strongly recommended!'),
          ];
        }
      }

      // Otherwise, ignore unpublished, insecure, or unsupported releases.
      if ($release['status'] == 'unpublished' ||
        (isset($release['terms']['Release type']) &&
          (in_array('Insecure', $release['terms']['Release type']) ||
            in_array('Unsupported', $release['terms']['Release type'])))) {
        continue;
      }

      $release_major_version = $release_module_version->getMajorVersion();
      // See if this is a higher major version than our target and yet still
      // supported. If so, record it as an "Also available" release.
      if ($release_major_version > $target_major) {
        if ($is_in_supported_branch($release['version'])) {
          if (!isset($this->projectData['also'])) {
            $this->projectData['also'] = [];
          }
          if (!isset($this->projectData['also'][$release_major_version])) {
            $this->projectData['also'][$release_major_version] = $version;
            $this->projectData['releases'][$version] = $release;
          }
        }
        // Otherwise, this release can't matter to us, since it's neither
        // from the release series we're currently using nor the recommended
        // release. We don't even care about security updates for this
        // branch, since if a project maintainer puts out a security release
        // at a higher major version and not at the lower major version,
        // they must remove the lower version from the supported major
        // versions at the same time, in which case we won't hit this code.
        continue;
      }

      // Look for the 'latest version' if we haven't found it yet. Latest is
      // defined as the most recent version for the target major version.
      if (!isset($this->projectData['latest_version'])
        && $release_major_version == $target_major) {
        $this->projectData['latest_version'] = $version;
        $this->projectData['releases'][$version] = $release;
      }

      // Look for the development snapshot release for this branch.
      if (!isset($this->projectData['dev_version'])
        && $release_major_version == $target_major
        && $release_module_version->getVersionExtra() === 'dev') {
        $this->projectData['dev_version'] = $version;
        $this->projectData['releases'][$version] = $release;
      }

      if ($release_module_version->getVersionExtra()) {
        $release_version_without_extra = str_replace('-' . $release_module_version->getVersionExtra(), '', $release['version']);
      }
      else {
        $release_version_without_extra = $release['version'];
      }

      // Look for the 'recommended' version if we haven't found it yet (see
      // phpdoc at the top of this function for the definition).
      if (!isset($this->projectData['recommended'])
        && $release_major_version == $target_major) {
        if ($recommended_version_without_extra !== $release_version_without_extra) {
          $recommended_version_without_extra = $release_version_without_extra;
          $recommended_release = $release;
        }
        if ($release_module_version->getVersionExtra() === NULL) {
          $this->projectData['recommended'] = $recommended_release['version'];
          $this->projectData['releases'][$recommended_release['version']] = $recommended_release;
        }
      }

      // Stop searching once we hit the currently installed version.
      if ($this->projectData['existing_version'] === $version) {
        break;
      }

      // If we're running a dev snapshot and have a timestamp, stop
      // searching for security updates once we hit an official release
      // older than what we've got. Allow 100 seconds of leeway to handle
      // differences between the datestamp in the .info.yml file and the
      // timestamp of the tarball itself (which are usually off by 1 or 2
      // seconds) so that we don't flag that as a new release.
      if ($this->projectData['install_type'] == 'dev') {
        if (empty($this->projectData['datestamp'])) {
          // We don't have current timestamp info, so we can't know.
          continue;
        }
        elseif (isset($release['date']) && ($this->projectData['datestamp'] + 100 > $release['date'])) {
          // We're newer than this, so we can skip it.
          continue;
        }
      }

      // See if this release is a security update.
      if (isset($release['terms']['Release type'])
        && in_array('Security update', $release['terms']['Release type'])) {
        $this->projectData['security updates'][] = $release;
      }
    }

    // If we were unable to find a recommended version, then make the latest
    // version the recommended version if possible.
    if (!isset($this->projectData['recommended']) && isset($this->projectData['latest_version'])) {
      $this->projectData['recommended'] = $this->projectData['latest_version'];
    }

    if (isset($this->projectData['status'])) {
      // If we already know the status, we're done.
      return;
    }

    // If we don't know what to recommend, there's nothing we can report.
    // Bail out early.
    if (!isset($this->projectData['recommended'])) {
      $this->projectData['status'] = UpdateFetcherInterface::UNKNOWN;
      $this->projectData['reason'] = t('No available releases found');
      return;
    }

    // If we're running a dev snapshot, compare the date of the dev snapshot
    // with the latest official version, and record the absolute latest in
    // 'latest_dev' so we can correctly decide if there's a newer release
    // than our current snapshot.
    if ($this->projectData['install_type'] == 'dev') {
      if (isset($this->projectData['dev_version']) && $this->availableReleases['releases'][$this->projectData['dev_version']]['date'] > $this->availableReleases['releases'][$this->projectData['latest_version']]['date']) {
        $this->projectData['latest_dev'] = $this->projectData['dev_version'];
      }
      else {
        $this->projectData['latest_dev'] = $this->projectData['latest_version'];
      }
    }

    // Figure out the status, based on what we've seen and the install type.
    switch ($this->projectData['install_type']) {
      case 'official':
        if ($this->projectData['existing_version'] === $this->projectData['recommended'] || $this->projectData['existing_version'] === $this->projectData['latest_version']) {
          $this->projectData['status'] = UpdateManagerInterface::CURRENT;
        }
        else {
          $this->projectData['status'] = UpdateManagerInterface::NOT_CURRENT;
        }
        break;

      case 'dev':
        $latest = $this->availableReleases['releases'][$this->projectData['latest_dev']];
        if (empty($this->projectData['datestamp'])) {
          $this->projectData['status'] = UpdateFetcherInterface::NOT_CHECKED;
          $this->projectData['reason'] = t('Unknown release date');
        }
        elseif (($this->projectData['datestamp'] + 100 > $latest['date'])) {
          $this->projectData['status'] = UpdateManagerInterface::CURRENT;
        }
        else {
          $this->projectData['status'] = UpdateManagerInterface::NOT_CURRENT;
        }
        break;

      default:
        $this->projectData['status'] = UpdateFetcherInterface::UNKNOWN;
        $this->projectData['reason'] = t('Invalid info');
    }
    return;
  }
}

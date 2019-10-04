<?php

namespace Drupal\update;

/**
 * Calculates a projects security coverage information.
 *
 * @internal
 */
class ProjectSecurityData {

  /**
   * The number of minor versions of Drupal core that are supported.
   */
  const CORE_MINORS_SUPPORTED = 2;

  /**
   * The Drupal project data.
   *
   * @var array
   *
   * @see \Drupal\update\UpdateManagerInterface::getProjects()
   * @see update_process_project_info()
   */
  protected $projectData;

  /**
   * Releases as returned by update_get_available().
   *
   * @var array
   *
   * @see update_get_available()
   */
  protected $releases;

  /**
   * Constructs a ProjectUpdateData object.
   *
   * @param array $project_data
   *   Project data from Drupal\update\UpdateManagerInterface::getProjects() and
   *   processed by update_process_project_info().
   * @param array $releases
   *   Project releases as returned by update_get_available().
   */
  public function __construct(array $project_data, array $releases) {
    $this->projectData = $project_data;
    $this->releases = $releases;
  }

  /**
   * Gets the security coverage information for a project.
   *
   * Currently only Drupal core is supported.
   *
   * @return array
   *   The security coverage information or an empty array if no security
   *   information is available for the project. If security coverage is based
   *   on support until a specific version the array will have the following
   *   keys:
   *   - support_end_version: The minor version the existing version
   *     is supported until.
   *   - additional_minors_coverage: The number of additional minor releases
   *     after the latest full release the existing version will be supported.
   *   If the support is based on support until a specific date the array will
   *   have the following keys:
   *   - support_end_date: The date support will end for the existing version
   *     in the format 'YYYY-MM-DD'
   *   - (optional) support_ending_warn_date: The date after which a warning
   *     should be displayed about upgrading to another version.
   */
  public function getCoverageInfo() {
    $info = [];
    if (!($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal')) {
      // Only Drupal core has an explicit coverage range.
      return [];
    }
    $minor_version = explode('.', $this->projectData['existing_version'])[1];
    // Support for Drupal 8's LTS release and the version before are based on
    // specific dates.
    if ($minor_version === '8') {
      $info['support_end_date'] = '2020-12-02';
      $info['support_ending_warn_date'] = '2020-6-02';
    }
    elseif ($minor_version === '9') {
      $info['support_end_date'] = '2021-11-01';
    }
    elseif ($support_until_release = $this->getSupportUntilReleaseInfo()) {
      $info['support_end_version'] = $support_until_release['version'];
      if ($this->isNextMajorReleasedWithoutSupportedReleased($support_until_release)) {
        // If the next major version has been released but
        // $support_until_release has not we cannot know the coverage status.
        return [];
      }
      $info['additional_minors_coverage'] = $this->getAdditionalSecuritySupportedMinors($support_until_release);
    }
    return $info;
  }

  /**
   * Gets information about the release the current minor is supported until.
   *
   * @todo In https://www.drupal.org/node/2608062 determine how we will know
   *    what the final minor release of a particular major version will be. This
   *    method should not return a version beyond that minor.
   *
   * @return array
   *   The release information.
   */
  private function getSupportUntilReleaseInfo() {
    if (empty($this->releases[$this->projectData['existing_version']])) {
      return [];
    }

    $existing_release = $this->releases[$this->projectData['existing_version']];
    if (!empty($existing_release['version_extra'])) {
      return [];
    }
    // Minors 8.8 and 8.9 have special logic for security support.
    // @see ::getLtsRequirement()
    if ($existing_release['version_major'] === '8' && in_array($existing_release['version_minor'], ['8', '9'])) {
      return [];
    }
    $support_until_release = [
      'version_major' => (int) $existing_release['version_major'],
      'version_minor' => ((int) $existing_release['version_minor']) + static::CORE_MINORS_SUPPORTED,
      'version_patch' => 0,
    ];
    $support_until_release['version'] = implode('.', $support_until_release);
    return $support_until_release;
  }

  /**
   * Determines if next major version is available and supported release is not.
   *
   * If the next major version is released but the version that the currently
   * installed version is supported till is not released then we cannot
   * determine if the currently installed version is within the support window.
   *
   * @param array $security_supported_release_info
   *   Release information as return by update_get_available().
   *
   * @return bool
   *   TRUE if the next major version has been released and the supported until
   *   release is not available.
   */
  private function isNextMajorReleasedWithoutSupportedReleased(array $security_supported_release_info) {
    $latest_full_release = $this->getMostRecentFullRelease();
    if ($latest_full_release['version_major'] > $security_supported_release_info['version_major']) {
      // Even if there is new major version we can know if the installed version
      // is not supported because the version it is supported till has already
      // been released.
      $latest_full_release = $this->getMostRecentFullRelease($security_supported_release_info['version_major']);
      if ($security_supported_release_info['version_minor'] > $latest_full_release['version_minor']) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the number of additional minor releases supported.
   *
   * @param array $security_supported_release_info
   *   The security supported release.
   *
   * @return int
   *   The number of additional supported minor releases.
   *
   * @throws \LogicException
   *   Throw when this method is called without checking if
   *   ::isNextMajorReleasedWithoutSupportedReleased() returns false.
   */
  private function getAdditionalSecuritySupportedMinors(array $security_supported_release_info) {
    $latest_full_release = $this->getMostRecentFullRelease();
    if ($latest_full_release['version_major'] > $security_supported_release_info['version_major']) {
      // Even if there is new major version we can know if the installed version
      // is not supported because the version it is supported till has already
      // been released.
      if ($latest_full_release = $this->getMostRecentFullRelease($security_supported_release_info['version_major'])) {
        if ($security_supported_release_info['version_minor'] <= $latest_full_release['version_minor']) {
          return -1;
        }
        else {
          throw new \LogicException('::getAdditionalSecuritySupportedMinors() should never be called before checking ::isNextMajorReleasedWithoutSupportedReleased().');
        }
      }
    }
    elseif ($latest_full_release['version_major'] === $security_supported_release_info['version_major']) {
      return $security_supported_release_info['version_minor'] - $latest_full_release['version_minor'];
    }
    // The latest full release was a lower major version.
    return -1;
  }

  /**
   * Gets the most recent full release.
   *
   * @param int|null $major
   *   (optional) Version major.
   *
   * @return array|null
   *   The most recent full release if found, otherwise NULL.
   */
  private function getMostRecentFullRelease($major = NULL) {
    foreach ($this->releases as $release) {
      $release['version_major'] = (int) $release['version_major'];
      if ($major && $release['version_major'] !== $major) {
        continue;
      }
      if ($release['status'] === 'published' && empty($release['version_extra'])) {
        $release['version_minor'] = (int) $release['version_minor'];
        $release['version_patch'] = (int) $release['version_patch'];
        return $release;
      }
    }
    return NULL;
  }

}

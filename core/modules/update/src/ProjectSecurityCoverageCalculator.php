<?php

namespace Drupal\update;

/**
 * Calculates a projects security coverage information.
 */
class ProjectSecurityCoverageCalculator {

  /**
   * The number of minor versions of Drupal core that are supported.
   */
  const CORE_MINORS_SUPPORTED = 2;

  /**
   * The Drupal project data.
   *
   * The data is obtained from
   * \Drupal\update\UpdateManagerInterface::getProjects() and then processed
   * @var array
   */
  protected $projectData;

  /**
   * Releases as returned by update_get_available().
   *
   * @see \update_get_available()
   *
   * @var array
   */
  protected $releases;

  /**
   * ProjectUpdateData constructor.
   */
  public function __construct(array $project_data, array $releases = NULL) {
    $this->projectData = $project_data;
    $this->releases = $releases;
  }

  /**
   * Gets the security coverage information for a project.
   *
   * Currently only Drupal core is supported.
   *
   * @return array
   *   The security coverage information.
   */
  public function getSecurityCoverageInfo() {
    $info = [];
    if (!($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal')) {
      // Only Drupal core has an explicit coverage range.
      return [];
    }
    if ($support_until_release = $this->getSupportUntilReleaseInfo()) {
      $info['supported_until_version'] = $support_until_release['version'];
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
   * @throws \Exception
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

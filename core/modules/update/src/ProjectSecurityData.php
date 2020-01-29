<?php

namespace Drupal\update;

/**
 * Calculates a project's security coverage information.
 *
 * @internal
 *   This class implements logic to determine security coverage for Drupal core
 *   according to Drupal core security policy. It should not be called directly.
 */
final class ProjectSecurityData {

  /**
   * The number of minor versions of Drupal core that are supported.
   */
  const CORE_MINORS_SUPPORTED = 2;

  /**
   * Define constants for versions with support end dates.
   *
   * Two types of constants are supported:
   * - SUPPORT_END_DATE_[VERSION_MAJOR]_[VERSION_MINOR]: A date in 'Y-m-d' or
   *   'Y-m' format.
   * - SUPPORT_ENDING_WARN_DATE__[VERSION_MAJOR]_[VERSION_MINOR]: A date in
   *   'Y-m-d' format.
   *
   * @see \Drupal\update\ProjectSecurityRequirement::getDateEndRequirement()
   */
  const SUPPORT_END_DATE_8_8 = '2020-12-02';

  const SUPPORT_ENDING_WARN_DATE_8_8 = '2020-06-02';

  const SUPPORT_END_DATE_8_9 = '2021-11';

  /**
   * Releases as returned by update_get_available().
   *
   * @var array
   *
   * Each release item in the array has metadata about that release. This class
   * uses the keys:
   * - status (string): The status of the release.
   * - version_major (string): The major version of the release.
   * - version_minor (string): The minor version of the release.
   * - version_extra (string): The extra string at the end of the version string
   *   such as '-dev', '-rc', '-alpha1', etc.
   *
   * @see update_get_available()
   */
  protected $releases;

  /**
   * The existing version of the project.
   *
   * Because this class only handles the Drupal core project values will be
   * semantic version numbers such as 8.8.0, 8.8.0-alpha1 or 9.0.0.
   *
   * @var string|null
   */
  protected $existingVersion;

  /**
   * Constructs a ProjectSecurityData object.
   *
   * @param string $existing_version
   *   The existing version of the project.
   * @param array $releases
   *   Project releases as returned by update_get_available().
   */
  private function __construct($existing_version = NULL, array $releases = []) {
    $this->existingVersion = $existing_version;
    $this->releases = $releases;
  }

  /**
   * Constructs a ProjectSecurityData object.
   *
   * @param array $project_data
   *   Project data from Drupal\update\UpdateManagerInterface::getProjects() and
   *   processed by update_process_project_info().
   * @param array $releases
   *   Project releases as returned by update_get_available().
   *
   * @return \Drupal\update\ProjectSecurityData
   *   The ProjectSecurityData instance.
   */
  public static function createFormProjectDataAndReleases(array $project_data, array $releases) {
    if (!($project_data['project_type'] === 'core' && $project_data['name'] === 'drupal')) {
      // Only Drupal core has an explicit coverage range.
      return new static();
    }
    return new static($project_data['existing_version'], $releases);
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
   *   - support_end_version (string): The minor version the existing version
   *     is supported until.
   *   - additional_minors_coverage (int): The number of additional minor
   *     releases after the latest full release the existing version will be
   *     supported.
   *   If the support is based on support until a specific date the array will
   *   have the following keys:
   *   - support_end_date (string): The month or date support will end for the
   *     existing version. It can be in either 'YYYY-MM' or 'YYYY-MM-DD' format.
   *   - (optional) support_ending_warn_date (string): The date, in the format
   *     'YYYY-MM-DD', after which a warning should be displayed about upgrading
   *     to another version.
   */
  public function getCoverageInfo() {
    if (empty($this->releases[$this->existingVersion])) {
      // If the existing version does not have a release we cannot get the
      // security coverage information.
      return [];
    }
    $info = [];
    $existing_release_version = ModuleVersion::createFromVersionString($this->existingVersion);

    // Check if the installed version has a specific end date defined.
    $version_suffix = $existing_release_version->getMajorVersion() . '_' . $this->getCoreMinorVersion($this->existingVersion);
    if (defined("self::SUPPORT_END_DATE_$version_suffix")) {
      $info['support_end_date'] = constant("self::SUPPORT_END_DATE_$version_suffix");
      $info['support_ending_warn_date'] = defined("self::SUPPORT_ENDING_WARN_DATE_$version_suffix") ? constant("self::SUPPORT_ENDING_WARN_DATE_$version_suffix") : NULL;
    }
    elseif ($support_until_release = $this->getSupportUntilReleaseInfo()) {
      $info['support_end_version'] = $support_until_release['version'];
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
   *   If release information is not available an empty array is returned
   *   otherwise the release information with the following keys:
   *   - version_major (int): The major version of the release.
   *   - version_minor (int): The minor version of the release.
   *   - version (string): The version number.
   */
  private function getSupportUntilReleaseInfo() {
    if (empty($this->releases[$this->existingVersion])) {
      return [];
    }

    $existing_release_version = ModuleVersion::createFromVersionString($this->existingVersion);
    if (!empty($existing_release_version->getVersionExtra())) {
      return [];
    }

    $support_until_release = [
      'version_major' => (int) $existing_release_version->getMajorVersion(),
      'version_minor' => $this->getCoreMinorVersion($this->existingVersion) + static::CORE_MINORS_SUPPORTED,
    ];
    $support_until_release['version'] = "{$support_until_release['version_major']}.{$support_until_release['version_minor']}.0";
    return $support_until_release;
  }

  /**
   * Gets the number of additional minor releases supported.
   *
   * @param array $security_supported_release_info
   *   The security supported release as returned by
   *   ::getSupportUntilReleaseInfo().
   *
   * @return int
   *   The number of additional supported minor releases.
   */
  private function getAdditionalSecuritySupportedMinors(array $security_supported_release_info) {
    foreach ($this->releases as $release) {
      $release_version = ModuleVersion::createFromVersionString($release['version']);
      if ((int) $release_version->getMajorVersion() === $security_supported_release_info['version_major'] && $release['status'] === 'published' && empty($release['version_extra'])) {
        $latest_minor = $this->getCoreMinorVersion($release['version']);
        break;
      }
    }
    return isset($latest_minor)
      ? $security_supported_release_info['version_minor'] - $latest_minor
      : NULL;
  }

  /**
   * Gets the minor version for a core version string.
   *
   * @param string $core_version
   *
   * @return int
   *   The minor version as an integer.
   */
  private function getCoreMinorVersion($core_version) {
    return (int) (explode('.', $core_version)[1]);
  }

}

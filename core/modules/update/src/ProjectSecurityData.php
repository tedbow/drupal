<?php

namespace Drupal\update;

/**
 * Calculates a project's security coverage information.
 *
 * @internal
 *   This class implements logic to determine security coverage for Drupal core
 *   according to Drupal core security policy. It should not be extended or
 *   called directly.
 */
class ProjectSecurityData {

  /**
   * The number of minor versions of Drupal core that are supported.
   */
  const CORE_MINORS_SUPPORTED = 2;

  /**
   * The Drupal project data.
   *
   * The following keys are used in this class:
   * - existing_version (string): The version of the project that is installed
   *   on the site.
   * - project_type (string): The type of project.
   * - name (string): The project machine name.
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
   * Each release item in the array has metadata about that release. This class
   * uses the keys:
   * - version_major (string): The major version of the release.
   * - version_minor (string): The minor version of the release.
   * - status (string): The status of the release.
   * - version_extra (string): The extra string at the end of the version string
   *   such as '-dev', '-rc', '-alpha1', etc.
   *
   * @see update_get_available()
   */
  protected $releases;

  /**
   * Constructs a ProjectSecurityData object.
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
    $info = [];
    if (!($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal')) {
      // Only Drupal core has an explicit coverage range.
      return [];
    }
    if (empty($this->releases[$this->projectData['existing_version']])) {
      // If the existing version does not have a release we cannot get the
      // security coverage information.
      return [];
    }
    $existing_release = $this->releases[$this->projectData['existing_version']];
    // Support for Drupal 8's LTS release and the version before are based on
    // specific dates.
    if ($existing_release['version_major'] === '8' && $existing_release['version_minor'] === '8') {
      $info['support_end_date'] = '2020-12-02';
      $info['support_ending_warn_date'] = '2020-06-02';
    }
    elseif ($existing_release['version_major'] === '8' && $existing_release['version_minor'] === '9') {
      $info['support_end_date'] = '2021-11';
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
    if (empty($this->releases[$this->projectData['existing_version']])) {
      return [];
    }

    $existing_release = $this->releases[$this->projectData['existing_version']];
    if (!empty($existing_release['version_extra'])) {
      return [];
    }

    $support_until_release = [
      'version_major' => (int) $existing_release['version_major'],
      'version_minor' => ((int) $existing_release['version_minor']) + static::CORE_MINORS_SUPPORTED,
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
      if ((int) $release['version_major'] === $security_supported_release_info['version_major'] && $release['status'] === 'published' && empty($release['version_extra'])) {
        $latest_minor = (int) $release['version_minor'];
        break;
      }
    }
    return isset($latest_minor)
      ? $security_supported_release_info['version_minor'] - $latest_minor
      : NULL;
  }

}

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
   * The number of minor versions of Drupal core that receive security coverage.
   *
   * For example, if this value is 2 and the existing version is 9.0.1, the
   * 9.0.x branch will receive security coverage until the release of version
   * 9.2.0.
   */
  const CORE_MINORS_WITH_SECURITY_COVERAGE = 2;

  /**
   * Define constants for versions with security coverage end dates.
   *
   * Two types of constants are supported:
   * - SECURITY_COVERAGE_END_DATE_[VERSION_MAJOR]_[VERSION_MINOR]: A date in
   *   'Y-m-d' or 'Y-m' format.
   * - SECURITY_COVERAGE_ENDING_WARN_DATE_[VERSION_MAJOR]_[VERSION_MINOR]: A
   *   date in 'Y-m-d' format.
   *
   * @see \Drupal\update\ProjectSecurityRequirement::getDateEndRequirement()
   */
  const SECURITY_COVERAGE_END_DATE_8_8 = '2020-12-02';

  const SECURITY_COVERAGE_ENDING_WARN_DATE_8_8 = '2020-06-02';

  const SECURITY_COVERAGE_END_DATE_8_9 = '2021-11';

  protected $coverageEndDate;

  /**
   * 
   * @var string|null
   */
  protected $coverageEndVersion;

  /**
   * @var int|null
   */
  protected $additionalMinorsCoverage;



  protected $coverageEndingWarnDate;

  /**
   * @return mixed|null
   */
  public function getCoverageEndingWarnDate() {
    return $this->coverageEndingWarnDate;
  }

  /**
   * @return mixed
   */
  public function getCoverageEndDate() {
    return $this->coverageEndDate;
  }

  /**
   * The version
   * @return string|null
   */
  public function getCoverageEndVersion() {
    return $this->coverageEndVersion;
  }

  /**
   * @return int|null
   */
  public function getAdditionalMinorsCoverage() {
    return $this->additionalMinorsCoverage;
  }

  /**
   * Constructs a ProjectSecurityData object.
   *
   * @param string $existing_version
   *   The existing (currently installed) version of the project.
   * @param array $releases
   *   Project releases as returned by update_get_available().
   */
  private function __construct($existing_version = NULL, array $releases = []) {
    $this->releases = $releases;

    if (empty($releases[$existing_version])) {
      // If the existing version does not have a release, we cannot get the
      // security coverage information.
      return;
    }
    $existing_release_version = ModuleVersion::createFromVersionString($existing_version);

    // Check if the installed version has a specific end date defined.
    $version_suffix = $existing_release_version->getMajorVersion() . '_' . $this->getSemanticMinorVersion($existing_version);
    if (defined("self::SECURITY_COVERAGE_END_DATE_$version_suffix")) {
      $this->coverageEndDate = constant("self::SECURITY_COVERAGE_END_DATE_$version_suffix");
      $this->coverageEndingWarnDate =
        defined("self::SECURITY_COVERAGE_ENDING_WARN_DATE_$version_suffix")
          ? constant("self::SECURITY_COVERAGE_ENDING_WARN_DATE_$version_suffix")
          : NULL;
    }
    elseif ($security_coverage_until_version = $this->getSecurityCoverageUntilVersion($existing_version)) {
      $this->coverageEndVersion = $security_coverage_until_version;
      $this->additionalMinorsCoverage = $this->getAdditionalSecurityCoveredMinors($security_coverage_until_version);
    }
  }

  /**
   * Creates a ProjectSecurityData object from project data and releases.
   *
   * @param array $project_data
   *   Project data from Drupal\update\UpdateManagerInterface::getProjects() and
   *   processed by update_process_project_info().
   * @param array $releases
   *   Project releases as returned by update_get_available().
   *
   * @return static
   */
  public static function createFromProjectDataAndReleases(array $project_data, array $releases) {
    if (!($project_data['project_type'] === 'core' && $project_data['name'] === 'drupal')) {
      throw new \UnexpectedValueException('\Drupal\update\ProjectSecurityData can only be used with Drupal core');
    }
    return new static($project_data['existing_version'], $releases);
  }

  /**
   * Gets the release the current minor will receive security coverage until.
   *
   * @todo In https://www.drupal.org/node/2608062 determine how we will know
   *    what the final minor release of a particular major version will be. This
   *    method should not return a version beyond that minor.
   *
   * @return string|null
   *   The version the existing version will receive security coverage until or
   *   NULL if this cannot be determined.
   */
  private static function getSecurityCoverageUntilVersion($existing_version) {
    $existing_release_version = ModuleVersion::createFromVersionString($existing_version);
    if (!empty($existing_release_version->getVersionExtra())) {
      // Only full releases receive security coverage.
      return NULL;
    }

    return $existing_release_version->getMajorVersion() . '.'
      . (static::getSemanticMinorVersion($existing_version) + static::CORE_MINORS_WITH_SECURITY_COVERAGE)
      . '.0';
  }

  /**
   * Gets the number of additional minor security covered releases.
   *
   * @param string $security_covered_version
   *   The version until which the existing version receives security coverage.
   *
   * @return int|null
   *   The number of additional minor releases that receive security coverage,
   *   or NULL if this cannot be determined.
   */
  private function getAdditionalSecurityCoveredMinors($security_covered_version) {
    $security_covered_version_major = ModuleVersion::createFromVersionString($security_covered_version)->getMajorVersion();
    $security_covered_version_minor = $this->getSemanticMinorVersion($security_covered_version);
    foreach ($this->releases as $release) {
      $release_version = ModuleVersion::createFromVersionString($release['version']);
      if ($release_version->getMajorVersion() === $security_covered_version_major && $release['status'] === 'published' && !$release_version->getVersionExtra()) {
        // The releases are ordered with the most recent releases first.
        // Therefore if we have found an official, published release with the
        // same major version as $security_covered_version then this release
        // can be used to determine the latest minor.
        $latest_minor = $this->getSemanticMinorVersion($release['version']);
        break;
      }
    }
    // If $latest_minor is set, we know that $latest_minor and
    // $security_covered_version_minor have the same major version. Therefore we
    // can simply subtract to determine the number of additional minor security
    // covered releases.
    return isset($latest_minor) ? $security_covered_version_minor - $latest_minor : NULL;
  }

  /**
   * Gets the minor version for a semantic version string.
   *
   * @param string $version
   *   The semantic version string.
   *
   * @return int
   *   The minor version as an integer.
   */
  private static function getSemanticMinorVersion($version) {
    return (int) (explode('.', $version)[1]);
  }

}

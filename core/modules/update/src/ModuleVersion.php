<?php

namespace Drupal\update;

/**
 * Provides a module version value object.
 *
 * @internal
 */
class ModuleVersion {

  /**
   * The version_string.
   *
   * @var string
   */
  protected $version;

  /**
   * The major version.
   *
   * @var string
   */
  protected $majorVersion;

  /**
   * The minor version.
   *
   * @var string|null
   */
  protected $minorVersion;

  /**
   * The patch version.
   *
   * @var string|null
   */
  protected $patchVersion;

  /**
   * The version extra string.
   *
   * @var string|null
   */
  protected $versionExtra;

  /**
   * Constructs a ModuleVersion object.
   *
   * @param string $version
   *   The version string.
   */
  public function __construct($version) {
    $this->version = $version;
    $version_parts = explode('.', $this->getVersionStringWithoutCoreCompatibility());
    $this->majorVersion = $version_parts[0];
    if (count($version_parts) === 2) {
      $last_version_part = $version_parts[1];
      $this->minorVersion = NULL;
    }
    else {
      $last_version_part = $version_parts[2];
      $this->minorVersion = $version_parts[1];
    }
    $last_version_split = explode('-', $last_version_part);
    // If patch equals 'x' this instance was created from a branch and the patch
    // version cannot be determined.
    $this->patchVersion = $last_version_split[0] === 'x' ? NULL : $last_version_split[0];
    $this->versionExtra = count($last_version_split) === 1 ? NULL : $last_version_split[1];
  }

  /**
   * Constructs a module version object from a support branch.
   *
   * This can be used to determine the major and minor versions. The patch
   * version will always be NULL.
   *
   * @param string $branch
   *   The support branch.
   *
   * @return \Drupal\update\ModuleVersion
   *   The module version instance.
   */
  public static function createFromSupportBranch($branch) {
    return new static($branch . 'x');
  }

  /**
   * Gets the major version.
   *
   * @return string
   *   The major version.
   */
  public function getMajorVersion() {
    return $this->majorVersion;
  }

  /**
   * Gets the minor version.
   *
   * @return string|null
   *   The minor version if available otherwise NULL.
   */
  public function getMinorVersion() {
    return $this->minorVersion;
  }

  /**
   * Gets the patch version.
   *
   * @return string
   *   The patch version.
   */
  public function getPatchVersion() {
    return $this->patchVersion;
  }

  /**
   * Gets the version string with the core compatibility prefix removed.
   *
   * @return string
   *   The version string.
   */
  private function getVersionStringWithoutCoreCompatibility() {
    return strpos($this->version, \Drupal::CORE_COMPATIBILITY) === 0 ? str_replace('8.x-', '', $this->version) : $this->version;
  }

  /**
   * Gets the version extra string at the end of the version number.
   *
   * @return string|null
   *   The version extra string if available otherwise NULL.
   */
  public function getVersionExtra() {
    return $this->versionExtra;
  }

  /**
   * Gets the support branch.
   *
   * @return string
   *   The support branch as is used in update XML files.
   */
  public function getSupportBranch() {
    $version = $this->version;
    if ($extra = $this->getVersionExtra()) {
      $version = str_replace("-$extra", '', $version);
    }
    $parts = explode('.', $version);
    array_pop($parts);
    return implode('.', $parts) . '.';
  }

}

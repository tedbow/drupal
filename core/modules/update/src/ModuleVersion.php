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
   * The version, without the core compatibility prefix, split apart by commas.
   *
   * @var array
   */
  protected $versionParts;

  /**
   * Constructs a ModuleVersion object.
   *
   * @param string $version
   *   The version string.
   */
  public function __construct($version) {
    $this->version = $version;
    $this->versionParts = explode('.', $this->getVersionStringWithoutCoreCompatibility());
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
    return new static ($branch . 'x');
  }

  /**
   * Gets the major version.
   *
   * @return string
   *   The major version.
   */
  public function getMajorVersion() {
    return $this->versionParts[0];
  }

  /**
   * Gets the minor version.
   *
   * @return string|null
   *   The minor version if available otherwise NULL.
   */
  public function getMinorVersion() {
    return count($this->versionParts) === 2 ? NULL : $this->versionParts[1];
  }

  /**
   * Gets the patch version.
   *
   * @return string
   *   The patch version.
   */
  public function getPatchVersion() {
    $last_version_part = count($this->versionParts) === 2 ? $this->versionParts[1] : $this->versionParts[2];
    $patch = explode('-', $last_version_part)[0];
    // If patch equals 'x' this instance was created from a branch and the patch
    // version cannot be determined.
    return $patch === 'x' ? NULL : $patch;
  }

  /**
   * Gets the version string with the core compatibility prefix removed.
   *
   * @return string
   *   The version string.
   */
  private function getVersionStringWithoutCoreCompatibility() {
    $version = strpos($this->version, \Drupal::CORE_COMPATIBILITY) === 0 ? str_replace('8.x-', '', $this->version) : $this->version;
    return $version;
  }

  /**
   * Gets the version extra string at the end of the version number.
   *
   * @return string|null
   *   The version extra string if available otherwise NULL.
   */
  public function getVersionExtra() {
    $last_version_parts = explode('-', count($this->versionParts) === 2 ? $this->versionParts[1] : $this->versionParts[2]);
    return count($last_version_parts) === 1 ? NULL : $last_version_parts[1];
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

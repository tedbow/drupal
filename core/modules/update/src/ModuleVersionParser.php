<?php

namespace Drupal\update;

/**
 * Provides a module version parser.
 */
class ModuleVersionParser {

  /**
   * The version_string.
   *
   * @var string
   */
  protected $version;

  /**
   * Constructs a ModuleVersionParser object.
   *
   * @param string $version
   *   The version string.
   */
  public function __construct($version) {
    $this->version = $version;
  }

  /**
   * Gets the major version.
   *
   * @return string
   *   The major version.
   */
  public function getMajorVersion() {
    return explode('.', $this->getVersionStringWithoutCoreCompatibility())[0];
  }

  /**
   * Gets the minor version.
   *
   * @return string|null
   *   The minor version if available otherwise NULL.
   */
  public function getMinorVersion() {
    $version_parts = explode('.', $this->getVersionStringWithoutCoreCompatibility());
    return count($version_parts) === 2 ? NULL : $version_parts[1];
  }

  /**
   * Gets the patch version.
   *
   * @return string
   *   The patch version.
   */
  public function getPatchVersion() {
    $version_parts = explode('.', $this->getVersionStringWithoutCoreCompatibility());
    $last_version_part = count($version_parts) === 2 ? $version_parts[1] : $version_parts[2];
    return explode('-', $last_version_part)[0];
  }

  /**
   * Gets the version string.
   *
   * @return string
   *   The version string.
   */
  private function getVersionStringWithoutCoreCompatibility() {
    $version = strpos($this->version, '8.x-') === 0 ? str_replace('8.x-', '', $this->version) : $this->version;
    return $version;
  }

  /**
   * Gets the version extra string at the end of the version number.
   *
   * @return string|null
   *   The version extra string if available otherwise NULL.
   */
  public function getVersionExtra() {
    $version_parts = explode('.', $this->getVersionStringWithoutCoreCompatibility());
    $last_version_parts = explode('-', count($version_parts) === 2 ? $version_parts[1] : $version_parts[2]);
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

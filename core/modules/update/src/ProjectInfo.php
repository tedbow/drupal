<?php

namespace Drupal\update;

/**
 * Provides a value object for update project data.
 */
class ProjectInfo {

  /**
   * The project data.
   *
   * @var array
   */
  protected $data;

  /**
   * Constructs an ProjectData object.
   *
   * @param array $data
   *   The project data.
   */
  public function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * Gets the major version.
   *
   * @return string
   *   The major version.
   */
  public function getMajorVersion() {
    return explode('.', $this->getVersionString())[0];
  }

  /**
   * Gets the minor version.
   *
   * @return string|null
   *   The minor version if available otherwise NULL.
   */
  public function getMinorVersion() {
    $version_parts = explode('.', $this->getVersionString());
    return count($version_parts) === 2 ? NULL : $version_parts[1];
  }

  /**
   * Gets the patch version.
   *
   * @return string
   *   The patch version.
   */
  public function getPatchVersion() {
    $version_parts = explode('.', $this->getVersionString());
    $last_version_part = count($version_parts) === 2 ? $version_parts[1] : $version_parts[2];
    return explode('-', $last_version_part)[0];
  }

  /**
   * Gets the version string.
   *
   * @return string
   *   The version string.
   */
  private function getVersionString() {
    $original_version = $this->data['version'];
    $version = strpos($original_version, '8.x-') === 0 ? str_replace('8.x-', '', $original_version) : $original_version;
    return $version;
  }

  /**
   * Gets the version extra string at the end of the version number.
   *
   * @return string|null
   *   The version extra string if available otherwise NULL.
   */
  public function getVersionExtra() {
    $version_parts = explode('.', $this->getVersionString());
    $last_version_parts = explode('-', count($version_parts) === 2 ? $version_parts[1] : $version_parts[2]);
    return count($last_version_parts) === 1 ? NULL : $last_version_parts[1];
  }

}

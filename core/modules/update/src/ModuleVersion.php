<?php

namespace Drupal\update;

/**
 * Provides a module version value object.
 *
 * @internal
 */
class ModuleVersion {

  /**
   * The core compatibility prefix used in version strings.
   */
  const CORE_COMPATIBILITY_PREFIX = \Drupal::CORE_COMPATIBILITY . '-';

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
   * Whether the core compatibility prefix should be used.
   *
   * @var bool
   */
  protected $useCorePrefix;

  /**
   * Constructs a module version object from a version string.
   *
   * @param string $version_string
   *   The version string.
   *
   * @return \Drupal\update\ModuleVersion
   *   The module version instance.
   */
  public static function createFromVersionString($version_string) {
    $use_compatibility_prefix = strpos($version_string, static::CORE_COMPATIBILITY_PREFIX) === 0;
    if ($use_compatibility_prefix) {
      $version_string = str_replace(static::CORE_COMPATIBILITY_PREFIX, '', $version_string);
    }
    $version_parts = explode('.', $version_string);
    $major_version = $version_parts[0];
    if (count($version_parts) === 2) {
      $last_version_part = $version_parts[1];
      $minor_version = NULL;
    }
    else {
      $last_version_part = $version_parts[2];
      $minor_version = $version_parts[1];
    }
    $last_version_split = explode('-', $last_version_part);
    // If patch equals 'x' this instance was created from a branch and the patch
    // version cannot be determined.
    $patch_version = $last_version_split[0] === 'x' ? NULL : $last_version_split[0];
    $version_extra = count($last_version_split) === 1 ? NULL : $last_version_split[1];
    return new static($major_version, $minor_version, $patch_version, $version_extra, $use_compatibility_prefix);
  }

  /**
   * Constructs a ModuleVersion object.
   *
   * @param string $major_version
   *   The major version.
   * @param string|null $minor_version
   *   The minor version.
   * @param string|null $patch_version
   *   The patch version.
   * @param string|null $version_extra
   *   The extra version string.
   * @param bool $use_core_compatibility_prefix
   *   Whether to use the core compatibility prefix.
   */
  protected function __construct($major_version, $minor_version, $patch_version, $version_extra, $use_core_compatibility_prefix) {
    $this->majorVersion = $major_version;
    $this->minorVersion = $minor_version;
    $this->patchVersion = $patch_version;
    $this->versionExtra = $version_extra;
    $this->useCorePrefix = $use_core_compatibility_prefix;
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
    return static::createFromVersionString($branch . 'x');
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
    $branch = $this->useCorePrefix ? static::CORE_COMPATIBILITY_PREFIX : '';
    $branch .= $this->majorVersion . '.';
    if ($this->minorVersion) {
      $branch .= $this->minorVersion . '.';
    }
    return $branch;
  }

}

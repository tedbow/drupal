<?php

namespace Drupal\update;

/**
 * Provides a module version value object.
 *
 * @internal
 */
class ModuleVersion {

  /**
   * @todo remove all methods except major and extra, maybe isInSupportBranch
   *
   */

  /**
   * The core compatibility prefix used in version strings.
   */
  const CORE_COMPATIBILITY_PREFIX = \Drupal::CORE_COMPATIBILITY . '-';

  protected $version;

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
    return new static($version_string);
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
  protected function __construct($version) {
    $this->version = $version;
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
    $version_string = strpos($this->version, static::CORE_COMPATIBILITY_PREFIX) === 0 ? str_replace(static::CORE_COMPATIBILITY_PREFIX, '', $this->version) : $this->version;
    return explode('.', $version_string)[0];
  }

  /**
   * Gets the version extra string at the end of the version number.
   *
   * @return string|null
   *   The version extra string if available otherwise NULL.
   */
  public function getVersionExtra() {
    $version_parts = explode('.', $this->version);
    $last_part_split = explode('-', array_pop($version_parts));
    return count($last_part_split) == 1 ? NULL : $last_part_split[1];
  }

}

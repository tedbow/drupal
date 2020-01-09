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
   *
   * @var string
   */
  const CORE_COMPATIBILITY_PREFIX = \Drupal::CORE_COMPATIBILITY . '-';

  /**
   * The major version.
   *
   * @var string
   */
  protected $majorVersion;

  /**
   * The version extra string.
   *
   * @var string|null
   */
  protected $versionExtra;

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
    $version_string = strpos($version_string, static::CORE_COMPATIBILITY_PREFIX) === 0 ? str_replace(static::CORE_COMPATIBILITY_PREFIX, '', $version_string) : $version_string;
    $version_parts = explode('.', $version_string);
    $major_version = $version_parts[0];
    $last_part_split = explode('-', array_pop($version_parts));
    $version_extra = count($last_part_split) === 1 ? NULL : $last_part_split[1];
    return new static($major_version, $version_extra);
  }

  /**
   * Constructs a ModuleVersion object.
   *
   * @param string $major_version
   *   The major version.
   * @param string|null $version_extra
   *   The extra version string.
   */
  protected function __construct($major_version, $version_extra) {
    $this->majorVersion = $major_version;
    $this->versionExtra = $version_extra;
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
   * Gets the version extra string at the end of the version number.
   *
   * @return string|null
   *   The version extra string if available otherwise NULL.
   */
  public function getVersionExtra() {
    return $this->versionExtra;
  }

}

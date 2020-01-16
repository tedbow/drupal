<?php

namespace Drupal\update;

/**
 * Provides a module version value object.
 *
 * @internal
 */
final class ModuleVersion {

  /**
   * The '8.x-' prefix is used on contrib module version numbers.
   *
   * @var string
   */
  const CORE_PREFIX = '8.x-';

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
    $original_version = $version_string;
    if (strpos($version_string, static::CORE_PREFIX) === 0) {
      $version_string = str_replace(static::CORE_PREFIX, '', $version_string);
    }
    else {
      // Ensure the version string has no unsupported core prefixes.
      $dot_x_position = strpos($version_string, '.x-');
      if ($dot_x_position === 1 || $dot_x_position === 2) {
        throw new \UnexpectedValueException("Unexpected version core prefix in $version_string. The only core prefix expected in \Drupal\update\ModuleVersion is '8.x-.");
      }
    }
    $version_parts = explode('.', $version_string);
    $major_version = $version_parts[0];
    $version_parts_count = count($version_parts);
    $last_part_split = explode('-', $version_parts[count($version_parts) - 1]);
    $version_extra = count($last_part_split) === 1 ? NULL : $last_part_split[1];
    if ($version_parts_count > 3 || $version_parts_count < 2
       || !is_numeric($major_version)
       || ($version_parts_count === 3 && !is_numeric($version_parts[1]))
       || (!is_numeric($last_part_split[0]) && $last_part_split !== 'x' && $version_extra !== 'dev')) {
      throw new \UnexpectedValueException("Unexpected version number in $original_version.");
    }
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
  private function __construct($major_version, $version_extra) {
    $this->majorVersion = $major_version;
    $this->versionExtra = $version_extra;
  }

  /**
   * Constructs a module version object from a support branch.
   *
   * This can be used to determine the major version of the branch.
   * ::getVersionExtra() will always return NULL for branches.
   *
   * @param string $branch
   *   The support branch.
   *
   * @return \Drupal\update\ModuleVersion
   *   The module version instance.
   */
  public static function createFromSupportBranch($branch) {
    if (substr($branch, -1) !== '.') {
      throw new \UnexpectedValueException("Invalid support branch: $branch");
    }
    return static::createFromVersionString($branch . '0');
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
   *   The version extra string if available, or otherwise NULL.
   */
  public function getVersionExtra() {
    return $this->versionExtra;
  }

}

<?php

namespace Drupal\update;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * Utility class to set core compatibility ranges.
 */
class UpdateProjectCoreCompatibility {

  /**
   * Set core_compatibility_ranges for project releases.
   *
   * @todo Update Security releases also.
   * @todo update core/modules/update/templates/update-version.html.twig to
   *   output the ranges.
   * @param array &$project_data
   * @param array $available_project
   * @param array $available_core
   */
  public static function setProjectCoreCompatibilityRanges(array $project_data, array &$project_releases, $core_data, array $core_releases) {
    if (!isset($core_data['existing_version']) || !isset($core_releases[$core_data['existing_version']])) {
      // If we can't determine the existing version then we can't calculate
      // the core compatibility of based on core versions after the existing
      // versions.
      return;
    }
    $releases_to_set = [];
    $possible_core_update_versions = self::getPossibleCoreUpdateVersions($core_data, $core_releases);
      foreach (['recommended', 'latest_version'] as $update_version_type) {
      if (isset($project_data[$update_version_type])&& !empty($project_releases[$project_data[$update_version_type]])) {
        $releases_to_set[] = &$project_releases[$project_data[$update_version_type]];
      }
    }
    if (!empty($project_data['also'])) {
      foreach ($project_data['also'] as $version) {
        if (isset($project_releases[$version])) {
          $releases_to_set[] = &$project_releases[$version];
        }
      }
    }
    foreach ($releases_to_set as &$release) {
      if (!empty($release['core_compatibility'])) {
        $release['core_compatibility_ranges'] = self::createCompatibilityRanges($release['core_compatibility'], $possible_core_update_versions);
        if ($release['core_compatibility_ranges']) {
          $release['core_compatibility_message'] = self::formatMessage($release['core_compatibility_ranges']);
        }
      }

    }

  }

  /**
   * @param $core_data
   * @param array $core_releases
   *
   * @return array
   */
  protected static function getPossibleCoreUpdateVersions($core_data, array $core_releases) {
    $core_release_versions = array_keys($core_releases);
    $possible_core_update_versions = Semver::satisfiedBy($core_release_versions, '>= ' . $core_data['existing_version']);
    $possible_core_update_versions = Semver::sort($possible_core_update_versions);
    $possible_core_update_versions = array_filter($possible_core_update_versions, function ($version) {
      return VersionParser::parseStability($version) === 'stable';
    });
    return $possible_core_update_versions;
  }

  /**
   * @param $core_compatibility
   * @param array $possible_core_update_versions
   *
   * @return array
   */
  protected static function createCompatibilityRanges($core_compatibility, array $possible_core_update_versions) {
    $compatibility_ranges = [];
    $previous_version_satisfied = NULL;
    $range = [];
    foreach ($possible_core_update_versions as $possible_core_update_version) {
      if (Semver::satisfies($possible_core_update_version, $core_compatibility)) {
        if (empty($range)) {
          $range[] = $possible_core_update_version;
        }
        else {
          $range[1] = $possible_core_update_version;
        }

      }
      else {
        if ($range) {
          if ($previous_version_satisfied) {
            // Make the previous version be the second item in the current
            // range.
            $range[] = $previous_version_satisfied;
          }
          $compatibility_ranges[] = $range;
        }
        // Start a new range.
        $range = [];
      }

    }
    if ($range) {
      $compatibility_ranges[] = $range;
    }
    return $compatibility_ranges;
  }

  protected static function formatMessage(array $core_compatibility_ranges) {
    $range_messages = [];
    foreach ($core_compatibility_ranges as $core_compatibility_range) {
      $range_message = $core_compatibility_range[0];
      if (count($core_compatibility_range) === 2) {
        $range_message .= " to {$core_compatibility_range[1]}";
      }
      $range_messages[] = $range_message;
    }
    return t('This module is compatible with Drupal core:') . ' ' . implode(', ', $range_messages);
  }

}

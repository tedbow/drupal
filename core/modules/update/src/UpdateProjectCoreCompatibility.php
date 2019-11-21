<?php

namespace Drupal\update;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * Utility class to set core compatibility ranges for available module updates.
 */
class UpdateProjectCoreCompatibility {

  /**
   * Set core_compatibility_ranges for project releases.
   *
   * @param array &$project_data
   *   The project data as returned by
   *   \Drupal\update\UpdateManagerInterface::getProjects() and then processed
   *   by update_process_project_info() and
   *   update_calculate_project_update_status().
   * @param array $core_data
   *   The project data for Drupal core as returned by
   *   \Drupal\update\UpdateManagerInterface::getProjects() and then processed
   *   by update_process_project_info() and
   *   update_calculate_project_update_status().
   * @param array $core_releases
   *   The drupal core available releases.
   */
  public static function setProjectCoreCompatibilityRanges(array &$project_data, $core_data, array $core_releases) {
    if (!isset($core_data['existing_version']) || !isset($core_releases[$core_data['existing_version']])) {
      // If we can't determine the existing version then we can't calculate
      // the core compatibility of based on core versions after the existing
      // version.
      return;
    }


    $possible_core_update_versions = self::getPossibleCoreUpdateVersions($core_data, $core_releases);

    // Get the various releases that will need to have a core compatibility
    // data added to them.
    $releases_to_set = [];
    $versions = [];
    if (!empty($project_data['recommended'])) {
      $versions[] = $project_data['recommended'];
    }
    if (!empty($project_data['latest_version'])) {
      $versions[] = $project_data['latest_version'];
    }
    if (!empty($project_data['also'])) {
      $versions = array_merge($versions, $project_data['also']);
    }
    foreach ($versions as $version) {
      if (isset($project_data['releases'][$version])) {
        $releases_to_set[] = &$project_data['releases'][$version];
      }
    }

    if (!empty($project_data['security updates'])) {
      foreach ($project_data['security updates'] as &$security_update) {
        $releases_to_set[] = &$security_update;
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
    // @todo statically cache the results here because this will the same for many projects
    // because they will likely use the same core_compatibility.
    // For example "^8 || ^9" or "^8.8 || ^9" should be very common.
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

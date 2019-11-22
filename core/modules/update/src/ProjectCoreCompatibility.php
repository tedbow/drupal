<?php

namespace Drupal\update;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * Utility class to set core compatibility ranges for available module updates.
 */
class ProjectCoreCompatibility {

  /**
   * Core versions that are available for updates.
   *
   * @var string[]
   */
  protected $possibleCoreUpdateVersions;

  /**
   * Core compatibility messages.
   *
   * @var string[]
   */
  protected $compatibilityMessages = [];

  /**
   * Constructs an UpdateProjectCoreCompatibility object.
   *
   * @param array $core_data
   *   The project data for Drupal core as returned by
   *   \Drupal\update\UpdateManagerInterface::getProjects() and then processed
   *   by update_process_project_info() and
   *   update_calculate_project_update_status().
   * @param array $core_releases
   *   The drupal core available releases.
   */
  public function __construct(array $core_data, array $core_releases) {
    if (isset($core_data['existing_version'])) {
      $this->possibleCoreUpdateVersions = $this->getPossibleCoreUpdateVersions($core_data['existing_version'], $core_releases);
    }
  }

  /**
   * Set core compatibility message for project releases.
   *
   * @param array &$project_data
   *   The project data as returned by
   *   \Drupal\update\UpdateManagerInterface::getProjects() and then processed
   *   by update_process_project_info() and
   *   update_calculate_project_update_status().
   */
  public function setReleaseMessage(array &$project_data) {
    if (empty($this->possibleCoreUpdateVersions)) {
      return;
    }

    // Get the various releases that will need to have core compatibility data
    // added to them.
    $releases_to_set = [];
    $versions = [];
    // 'recommended' and 'latest' will be single version numbers if set.
    if (!empty($project_data['recommended'])) {
      $versions[] = $project_data['recommended'];
    }
    if (!empty($project_data['latest_version'])) {
      $versions[] = $project_data['latest_version'];
    }
    // If set 'also' will be an array of version numbers.
    if (!empty($project_data['also'])) {
      $versions = array_merge($versions, $project_data['also']);
    }
    foreach ($versions as $version) {
      if (isset($project_data['releases'][$version])) {
        $releases_to_set[] = &$project_data['releases'][$version];
      }
    }

    // If 'security updates' exists it will be array of releases.
    if (!empty($project_data['security updates'])) {
      foreach ($project_data['security updates'] as &$security_update) {
        $releases_to_set[] = &$security_update;
      }
    }

    foreach ($releases_to_set as &$release) {
      if (!empty($release['core_compatibility'])) {
        $release['core_compatibility_message'] = $this->createMessageFromCoreCompatibility($release['core_compatibility']);
      }
    }
  }

  /**
   * Gets the core versions that should be considered for compatibility ranges.
   *
   * @param string $existing_version
   *   The core existing version.
   * @param array $core_releases
   *   The drupal core available releases.
   *
   * @return string[]
   *   The core version numbers.
   */
  protected function getPossibleCoreUpdateVersions($existing_version, array $core_releases) {
    if (!isset($core_releases[$existing_version])) {
      // If we can't determine the existing version then we can't calculate
      // the core compatibility of based on core versions after the existing
      // version.
      return [];
    }
    $core_release_versions = array_keys($core_releases);
    $possible_core_update_versions = Semver::satisfiedBy($core_release_versions, '>= ' . $existing_version);
    $possible_core_update_versions = Semver::sort($possible_core_update_versions);
    $possible_core_update_versions = array_filter($possible_core_update_versions, function ($version) {
      return VersionParser::parseStability($version) === 'stable';
    });
    return $possible_core_update_versions;
  }

  /**
   * Gets the compatibility ranges for the a constraint.
   *
   * @param string $core_compatibility_constraint
   *   A Composer Semver compatible constraint.
   *
   * @return array[]
   *   An array compatibility ranges. If the range array has 2 element then this
   *   denotes a range of compatibility between and including the 2 versions. If
   *   the range has 1 element then it denotes compatibility with a single
   *   version.
   */
  protected function getCompatibilityRanges($core_compatibility_constraint) {
    $compatibility_ranges = [];
    $previous_version_satisfied = NULL;
    $range = [];
    foreach ($this->possibleCoreUpdateVersions as $possible_core_update_version) {
      if (Semver::satisfies($possible_core_update_version, $core_compatibility_constraint)) {
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

  /**
   * Creates core compatibility message.
   *
   * @param string $core_compatibility_constraint
   *   A Composer Semver compatible constraint.
   *
   * @return string
   *   The core compatibility message.
   */
  protected function createMessageFromCoreCompatibility($core_compatibility_constraint) {
    if (!isset($this->compatibilityMessages[$core_compatibility_constraint])) {
      $core_compatibility_ranges = $this->getCompatibilityRanges($core_compatibility_constraint);
      $range_messages = [];
      foreach ($core_compatibility_ranges as $core_compatibility_range) {
        if (count($core_compatibility_range) === 2) {
          $range_messages[] = t('@start to @end', ['@start' => $core_compatibility_range[0], '@end' => $core_compatibility_range[1]]);
        }
        else {
          $range_messages[] = $core_compatibility_range[0];
        }
      }
      $this->compatibilityMessages[$core_compatibility_constraint] = t('This module is compatible with Drupal core:') . ' ' . implode(', ', $range_messages);
    }
    return $this->compatibilityMessages[$core_compatibility_constraint];
  }

}

<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\update\UpdateProjectCoreCompatibility;

/**
 * @coversDefaultClass \Drupal\update\UpdateProjectCoreCompatibility
 */
class UpdateProjectCoreCompatibilityTest extends UnitTestCase {

  /**
   * @covers ::setProjectCoreCompatibilityRanges
   * @dataProvider providerSetProjectCoreCompatibilityRanges
   */
  public function testSetProjectCoreCompatibilityRanges(array $project_data, array $project_releases, $core_data, array $core_releases, array $expected_ranges) {
    UpdateProjectCoreCompatibility::setProjectCoreCompatibilityRanges($project_data, $project_releases, $core_data, $core_releases);
    $actual_ranges = [];
    foreach ($project_releases as $project_version => $project_release) {
      if (isset($project_release['core_compatibility_ranges'])) {
        $actual_ranges[$project_version] = $project_release['core_compatibility_ranges'];
      }
    }
    $this->assertSame($expected_ranges, $actual_ranges);
  }

  public function providerSetProjectCoreCompatibilityRanges() {
    $test_cases['no 9 releases'] = [
      'project_data' => [
        'recommended' => '1.0.1',
        'latest_version' => '1.2.3',
        'also' => [
          '1.2.4',
          '1.2.5',
          '1.2.6',
        ],
      ],
      'project_releases' => [
        '1.0.1' => [
          'core_compatibility' => '^8.8 || ^9',
        ],
        '1.2.3' => [
          'core_compatibility' => '^8.9 || ^9',
        ],
        '1.2.4' => [
          'core_compatibility' => '^8.9.2 || ^9',
        ],
        '1.2.5' => [
          'core_compatibility' => '8.9.0 || 8.9.2 || ^9.0.1',
        ],
        '1.2.6' => [],
      ],
      'core_data' => [
        'existing_version' => '8.8.0',
      ],
      'core_releases' => [
        '8.8.0-alpha1' => [],
        '8.8.0-beta1' => [],
        '8.8.0-rc1' => [],
        '8.8.0' => [],
        '8.8.1' => [],
        '8.8.2' => [],
        '8.9.0' => [],
        '8.9.1' => [],
        '8.9.2' => [],
      ],
      'expected_ranges' => [
        '1.0.1' => [['8.8.1', '8.9.2']],
        '1.2.3' => [['8.9.0', '8.9.2']],
        '1.2.4' => [['8.9.2']],
        '1.2.5' => [['8.9.0'], ['8.9.2']],
      ],
    ];
    // Ensure that when only Drupal 9 pre-releases none of the expected ranges
    // change.
    $test_cases['with 9 pre releases'] = $test_cases['no 9 releases'];
    $test_cases['with 9 pre releases']['core_releases'] += [
      '9.0.0-alpha1' => [],
      '9.0.0-beta1' => [],
      '9.0.0-rc1' => [],
    ];
    // Ensure that when the Drupal 9 full release are added the expected ranges
    // do change.
    $test_cases['with 9 full releases'] = $test_cases['with 9 pre releases'];
    $test_cases['with 9 full releases']['core_releases'] += [
      '9.0.0' => [],
      '9.0.1' => [],
      '9.0.2' => [],
    ];
    $test_cases['with 9 full releases']['expected_ranges'] = [
      '1.0.1' => [['8.8.1', '9.0.2']],
      '1.2.3' => [['8.9.0', '9.0.2']],
      '1.2.4' => [['8.9.2', '9.0.2']],
      '1.2.5' => [
        ['8.9.0'],
        ['8.9.2'],
        ['9.0.1', '9.0.2'],
      ],
    ];
    return $test_cases;
  }

}

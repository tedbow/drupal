<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\update\ProjectInfo;

/**
 * @coversDefaultClass \Drupal\update\ProjectInfo
 *
 * @group update
 */
class ProjectInfoTest extends UnitTestCase {

  /**
   * @covers ::getMajorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMajorVersion($version, $excepted_version_info) {
    $projectInfo = new ProjectInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['major'], $projectInfo->getMajorVersion());
  }

  /**
   * @covers ::getMinorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMinorVersion($version, $excepted_version_info) {
    $projectInfo = new ProjectInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['minor'], $projectInfo->getMinorVersion());
  }

  /**
   * @covers ::getPatchVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetPatchVersion($version, $excepted_version_info) {
    $projectInfo = new ProjectInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['patch'], $projectInfo->getPatchVersion());
  }

  /**
   * @covers ::getVersionExtra
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetVersionExtra($version, $excepted_version_info) {
    $projectInfo = new ProjectInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['extra'], $projectInfo->getVersionExtra());
  }

  /**
   * Dataprovider for expected version information.
   *
   * @return array
   *   Arrays of version information.
   */
  public function providerVersionInfos() {
    return [
      '8.x-1.3' => [
        '8.x-1.3',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => NULL,
        ],
      ],
      '8.x-1.3-dev' => [
        '8.x-1.3-dev',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => 'dev',
        ],
      ],
      '1.3' => [
        '1.3',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => NULL,
        ],
      ],
      '1.3-dev' => [
        '1.3-dev',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => 'dev',
        ],
      ],
      '1.2.3' => [
        '1.2.3',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => '3',
          'extra' => NULL,
        ],
      ],
      '1.2.3-dev' => [
        '1.2.3-dev',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => '3',
          'extra' => 'dev',
        ],
      ],
    ];
  }

}

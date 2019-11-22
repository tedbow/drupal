<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\update\ReleaseInfo;

/**
 * @coversDefaultClass \Drupal\update\ReleaseInfo
 *
 * @group update
 */
class ReleaseInfoTest extends UnitTestCase {

  /**
   * @covers ::getMajorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMajorVersion($version, $excepted_version_info) {
    $releaseInfo = new ReleaseInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['major'], $releaseInfo->getMajorVersion());
  }

  /**
   * @covers ::getMinorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMinorVersion($version, $excepted_version_info) {
    $releaseInfo = new ReleaseInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['minor'], $releaseInfo->getMinorVersion());
  }

  /**
   * @covers ::getPatchVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetPatchVersion($version, $excepted_version_info) {
    $releaseInfo = new ReleaseInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['patch'], $releaseInfo->getPatchVersion());
  }

  /**
   * @covers ::getVersionExtra
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetVersionExtra($version, $excepted_version_info) {
    $releaseInfo = new ReleaseInfo(['version' => $version]);
    $this->assertSame($excepted_version_info['extra'], $releaseInfo->getVersionExtra());
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

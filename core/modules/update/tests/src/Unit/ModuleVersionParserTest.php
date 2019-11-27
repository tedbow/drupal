<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\update\ModuleVersionParser;

/**
 * @coversDefaultClass \Drupal\update\ModuleVersionParser
 *
 * @group update
 */
class ModuleVersionParserTest extends UnitTestCase {

  /**
   * @covers ::getMajorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMajorVersion($version, $excepted_version_info) {
    $releaseInfo = new ModuleVersionParser($version);
    $this->assertSame($excepted_version_info['major'], $releaseInfo->getMajorVersion());
  }

  /**
   * @covers ::getMinorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMinorVersion($version, $excepted_version_info) {
    $releaseInfo = new ModuleVersionParser($version);
    $this->assertSame($excepted_version_info['minor'], $releaseInfo->getMinorVersion());
  }

  /**
   * @covers ::getPatchVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetPatchVersion($version, $excepted_version_info) {
    $releaseInfo = new ModuleVersionParser($version);
    $this->assertSame($excepted_version_info['patch'], $releaseInfo->getPatchVersion());
  }

  /**
   * @covers ::getVersionExtra
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetVersionExtra($version, $excepted_version_info) {
    $releaseInfo = new ModuleVersionParser($version);
    $this->assertSame($excepted_version_info['extra'], $releaseInfo->getVersionExtra());
  }

  /**
   * @covers ::getSupportBranch
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetSupportBranch($version, $excepted_version_info) {
    $releaseInfo = new ModuleVersionParser($version);
    $this->assertSame($excepted_version_info['branch'], $releaseInfo->getSupportBranch());
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
          'branch' => '8.x-1.',
        ],
      ],
      '8.x-1.3-dev' => [
        '8.x-1.3-dev',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => 'dev',
          'branch' => '8.x-1.',
        ],
      ],
      '1.3' => [
        '1.3',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => NULL,
          'branch' => '1.',
        ],
      ],
      '1.3-dev' => [
        '1.3-dev',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '3',
          'extra' => 'dev',
          'branch' => '1.',
        ],
      ],
      '1.2.3' => [
        '1.2.3',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => '3',
          'extra' => NULL,
          'branch' => '1.2.',
        ],
      ],
      '1.2.3-dev' => [
        '1.2.3-dev',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => '3',
          'extra' => 'dev',
          'branch' => '1.2.',
        ],
      ],
    ];
  }

}

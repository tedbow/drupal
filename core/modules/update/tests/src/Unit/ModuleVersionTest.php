<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\update\ModuleVersion;

/**
 * @coversDefaultClass \Drupal\update\ModuleVersion
 *
 * @group update
 */
class ModuleVersionTest extends UnitTestCase {

  /**
   * @covers ::getMajorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMajorVersion($version, $expected_version_info) {
    $version = ModuleVersion::createFromVersionString($version);
    $this->assertSame($expected_version_info['major'], $version->getMajorVersion());
  }

  /**
   * @covers ::getMinorVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetMinorVersion($version, $expected_version_info) {
    $version = ModuleVersion::createFromVersionString($version);
    $this->assertSame($expected_version_info['minor'], $version->getMinorVersion());
  }

  /**
   * @covers ::getPatchVersion
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetPatchVersion($version, $expected_version_info) {
    $version = ModuleVersion::createFromVersionString($version);
    $this->assertSame($expected_version_info['patch'], $version->getPatchVersion());
  }

  /**
   * @covers ::getVersionExtra
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetVersionExtra($version, $expected_version_info) {
    $version = ModuleVersion::createFromVersionString($version);
    $this->assertSame($expected_version_info['extra'], $version->getVersionExtra());
  }

  /**
   * @covers ::createFromSupportBranch
   *
   * @dataProvider providerCreateFromSupportBranch
   */
  public function testCreateFromSupportBranch($branch, $expected_major, $expected_minor) {
    $version = ModuleVersion::createFromSupportBranch($branch);
    $this->assertInstanceOf(ModuleVersion::class, $version);
    $this->assertSame($expected_major, $version->getMajorVersion());
    $this->assertSame($expected_minor, $version->getMinorVersion());
    // Version extra and Patch version can't be determined from a branch.
    $this->assertSame(NULL, $version->getVersionExtra());
    $this->assertSame(NULL, $version->getPatchVersion());
  }

  /**
   * Data provider for providerCreateFromSupportBranch().
   */
  public function providerCreateFromSupportBranch() {
    return [
      '0.' => [
        '0.',
        '0',
        NULL,
      ],
      '1.' => [
        '1.',
        '1',
        NULL,
      ],
      '0.1.' => [
        '0.1.',
        '0',
        '1',
      ],
      '1.2.' => [
        '1.2.',
        '1',
        '2',
      ],
    ];
  }

  /**
   * Data provider for expected version information.
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
      '8.x-1.0' => [
        '8.x-1.0',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '0',
          'extra' => NULL,
        ],
      ],
      '8.x-1.0-dev' => [
        '8.x-1.0-dev',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '0',
          'extra' => 'dev',
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
      '0.1' => [
        '0.1',
        [
          'major' => '0',
          'minor' => NULL,
          'patch' => '1',
          'extra' => NULL,
        ],
      ],
      '1.0' => [
        '1.0',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '0',
          'extra' => NULL,
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
      '1.0-dev' => [
        '1.0-dev',
        [
          'major' => '1',
          'minor' => NULL,
          'patch' => '0',
          'extra' => 'dev',
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
      '0.2.0' => [
        '0.2.0',
        [
          'major' => '0',
          'minor' => '2',
          'patch' => '0',
          'extra' => NULL,
        ],
      ],
      '1.2.0' => [
        '1.2.0',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => '0',
          'extra' => NULL,
        ],
      ],
      '1.0.3' => [
        '1.0.3',
        [
          'major' => '1',
          'minor' => '0',
          'patch' => '3',
          'extra' => NULL,
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
      '1.2.0-dev' => [
        '1.2.0-dev',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => '0',
          'extra' => 'dev',
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
      '1.0.x' => [
        '1.0.x',
        [
          'major' => '1',
          'minor' => '0',
          'patch' => NULL,
          'extra' => NULL,
        ],
      ],
      '1.2.x' => [
        '1.2.x',
        [
          'major' => '1',
          'minor' => '2',
          'patch' => NULL,
          'extra' => NULL,
        ],
      ],
    ];
  }

  /**
   * @covers ::isInSupportBranch
   *
   * @dataProvider providerIsInSupportBranch
   */
  public function testIsInSupportBranch($version, $branch, $expected_result) {
    $this->assertEquals($expected_result, ModuleVersion::createFromVersionString($version)->isInSupportBranch($branch));
  }

  /**
   * Data provider for testIsInSupportBranch().
   */
  public function providerIsInSupportBranch() {
    return [
      [
        '1.2.3',
        '1.',
        TRUE,
      ],
      [
        '1.2.3',
        '1.2.',
        TRUE,
      ],
      [
        '1.2.3',
        '1.3.',
        FALSE,
      ],
      [
        '1.2.3',
        '2.',
        FALSE,
      ],
      [
        '1.2.3',
        '2.2.',
        FALSE,
      ],
      [
        '1.2.3',
        '2.3.',
        FALSE,
      ],
    ];
  }

}

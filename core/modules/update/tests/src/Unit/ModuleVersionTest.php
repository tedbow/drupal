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
   * @covers ::getVersionExtra
   *
   * @dataProvider providerVersionInfos
   */
  public function testGetVersionExtra($version, $expected_version_info) {
    $version = ModuleVersion::createFromVersionString($version);
    $this->assertSame($expected_version_info['extra'], $version->getVersionExtra());
  }

  /**
   * @covers ::createFromVersionString
   *
   * @dataProvider providerInvalidVersionCorePrefix
   */
  public function testInvalidVersionCorePrefix($version_string) {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Unexpected version core prefix in $version_string. The only core prefix expected in \Drupal\update\ModuleVersion is '8.x-.");
    ModuleVersion::createFromVersionString($version_string);
  }

  /**
   * @covers ::createFromSupportBranch
   *
   * @dataProvider providerInvalidBranchCorePrefix
   */
  public function testInvalidBranchCorePrefix($branch) {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Unexpected version core prefix in {$branch}x. The only core prefix expected in \Drupal\update\ModuleVersion is '8.x-.");
    ModuleVersion::createFromSupportBranch($branch);
  }

  /**
   * @covers ::createFromSupportBranch
   *
   * @dataProvider providerCreateFromSupportBranch
   */
  public function testCreateFromSupportBranch($branch, $expected_major) {
    $version = ModuleVersion::createFromSupportBranch($branch);
    $this->assertInstanceOf(ModuleVersion::class, $version);
    $this->assertSame($expected_major, $version->getMajorVersion());
    // Version extra can't be determined from a branch.
    $this->assertSame(NULL, $version->getVersionExtra());
  }

  /**
   * Data provider for providerCreateFromSupportBranch().
   */
  public function providerCreateFromSupportBranch() {
    return [
      '0.' => [
        '0.',
        '0',
      ],
      '1.' => [
        '1.',
        '1',
      ],
      '0.1.' => [
        '0.1.',
        '0',
      ],
      '1.2.' => [
        '1.2.',
        '1',
      ],
      '8.x-1.' => [
        '8.x-1.',
        '1',
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
          'extra' => NULL,
        ],
      ],
      '8.x-1.0' => [
        '8.x-1.0',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '8.x-1.0-dev' => [
        '8.x-1.0-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '8.x-1.3-dev' => [
        '8.x-1.3-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '0.1' => [
        '0.1',
        [
          'major' => '0',
          'extra' => NULL,
        ],
      ],
      '1.0' => [
        '1.0',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '1.3' => [
        '1.3',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '1.0-dev' => [
        '1.0-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '1.3-dev' => [
        '1.3-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '0.2.0' => [
        '0.2.0',
        [
          'major' => '0',
          'extra' => NULL,
        ],
      ],
      '1.2.0' => [
        '1.2.0',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '1.0.3' => [
        '1.0.3',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '1.2.3' => [
        '1.2.3',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '1.2.0-dev' => [
        '1.2.0-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '1.2.3-dev' => [
        '1.2.3-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '1.0.x' => [
        '1.0.x',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
      '1.2.x' => [
        '1.2.x',
        [
          'major' => '1',
          'extra' => NULL,
        ],
      ],
    ];
  }

  /**
   * Data provider for testInvalidVersionCorePrefix().
   */
  public function providerInvalidVersionCorePrefix() {
    return [
      ['6.x-1.0'],
      ['7.x-1.x'],
      ['9.x-1.x'],
      ['10.x-1.x'],
    ];
  }

  /**
   * Data provider for testInvalidBranchCorePrefix().
   */
  public function providerInvalidBranchCorePrefix() {
    return [
      ['6.x-1.'],
      ['7.x-1.'],
      ['9.x-1.'],
      ['10.x-1.'],
    ];
  }

}

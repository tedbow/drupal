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
      '8.x-1.0-alpha1' => [
        '8.x-1.0-alpha1',
        [
          'major' => '1',
          'extra' => 'alpha1',
        ],
      ],
      '8.x-1.3-alpha1' => [
        '8.x-1.3-alpha1',
        [
          'major' => '1',
          'extra' => 'alpha1',
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
      '1.0-alpha1' => [
        '1.0-alpha1',
        [
          'major' => '1',
          'extra' => 'alpha1',
        ],
      ],
      '1.3-alpha1' => [
        '1.3-alpha1',
        [
          'major' => '1',
          'extra' => 'alpha1',
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
      '1.2.0-alpha1' => [
        '1.2.0-alpha1',
        [
          'major' => '1',
          'extra' => 'alpha1',
        ],
      ],
      '1.2.3-alpha1' => [
        '1.2.3-alpha1',
        [
          'major' => '1',
          'extra' => 'alpha1',
        ],
      ],
      '8.x-1.x-dev' => [
        '8.x-1.x-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '8.x-8.x-dev' => [
        '8.x-8.x-dev',
        [
          'major' => '8',
          'extra' => 'dev',
        ],
      ],
      '1.x-dev' => [
        '1.x-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '8.x-dev' => [
        '8.x-dev',
        [
          'major' => '8',
          'extra' => 'dev',
        ],
      ],
      '1.0.x-dev' => [
        '1.0.x-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
      '1.2.x-dev' => [
        '1.2.x-dev',
        [
          'major' => '1',
          'extra' => 'dev',
        ],
      ],
    ];
  }

  /**
   * @covers ::createFromVersionString
   *
   * @dataProvider providerInvalidVersionNumber
   */
  public function testInvalidVersionNumber($version_string) {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Unexpected version number in: $version_string");
    ModuleVersion::createFromVersionString($version_string);
  }

  /**
   * Data provider for testInvalidVersionNumber().
   */
  public function providerInvalidVersionNumber() {
    return static::createKeyedTestCases([
      '',
      '8',
      'x',
      'xx',
      '8.x-',
      '8.x',
      '.x',
      '.0',
      '.1',
      '.1.0',
      '1.0.',
      'x.1',
      '1.x.0',
      '1.1.x',
      '1.1.x-extra',
      'x.1.1',
      '1.1.1.1',
      '1.1.1.0',
    ]);
  }

  /**
   * @covers ::createFromVersionString
   *
   * @dataProvider providerInvalidVersionCorePrefix
   */
  public function testInvalidVersionCorePrefix($version_string) {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Unexpected version core prefix in $version_string. The only core prefix expected in \Drupal\update\ModuleVersion is: 8.x-");
    ModuleVersion::createFromVersionString($version_string);
  }

  /**
   * Data provider for testInvalidVersionCorePrefix().
   */
  public function providerInvalidVersionCorePrefix() {
    return static::createKeyedTestCases([
      '6.x-1.0',
      '7.x-1.x',
      '9.x-1.x',
      '10.x-1.x',
    ]);
  }

  /**
   * @covers ::createFromSupportBranch
   *
   * @dataProvider providerInvalidBranchCorePrefix
   *
   * @param string $branch
   *   The branch to test.
   */
  public function testInvalidBranchCorePrefix($branch) {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Unexpected version core prefix in {$branch}0. The only core prefix expected in \Drupal\update\ModuleVersion is: 8.x-");
    ModuleVersion::createFromSupportBranch($branch);
  }

  /**
   * Data provider for testInvalidBranchCorePrefix().
   */
  public function providerInvalidBranchCorePrefix() {
    return static::createKeyedTestCases([
      '6.x-1.',
      '7.x-1.',
      '9.x-1.',
      '10.x-1.',
    ]);
  }

  /**
   * @covers ::createFromSupportBranch
   *
   * @dataProvider providerCreateFromSupportBranch
   *
   * @param string $branch
   *   The branch to test.
   *
   * @param string $expected_major
   *   The expected major version.
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
   * @covers ::createFromSupportBranch
   *
   * @dataProvider provideInvalidBranch
   *
   * @param string $branch
   *   The branch to test.
   */
  public function testInvalidBranch($branch) {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Invalid support branch: $branch");
    ModuleVersion::createFromSupportBranch($branch);
  }

  /**
   * Data provider for testInvalidBranch().
   */
  public function provideInvalidBranch() {
    return self::createKeyedTestCases([
      '8.x-1.0',
      '8.x-2.x',
      '2.x-1.0',
      '1.1',
      '1.x',
      '1.1.x',
      '1.1.1',
      '1.1.1.1',
    ]);
  }

  /**
   * Creates test case arrays for data provider methods.
   *
   * @param string[] $test_arguments
   *   The test arguments.
   *
   * @return array
   *   An array with $test_arguments as keys and each element of $test_arguments
   *   as a single item array
   */
  protected static function createKeyedTestCases(array $test_arguments) {
    return array_combine(
      $test_arguments,
      array_map(function ($test_argument) {
        return [$test_argument];
      }, $test_arguments)
    );
  }

}

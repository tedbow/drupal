<?php

namespace Drupal\Tests\Core\Extension;

use Drupal\Core\Extension\InfoParser;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * Tests InfoParser class and exception.
 *
 * Files for this test are stored in core/modules/system/tests/fixtures and end
 * with .info.txt instead of info.yml in order not not be considered as real
 * extensions.
 *
 * @coversDefaultClass \Drupal\Core\Extension\InfoParser
 *
 * @group Extension
 */
class InfoParserUnitTest extends UnitTestCase {

  /**
   * The InfoParser object.
   *
   * @var \Drupal\Core\Extension\InfoParser
   */
  protected $infoParser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->infoParser = new InfoParser();
  }

  /**
   * Tests the functionality of the infoParser object.
   *
   * @covers ::parse
   */
  public function testInfoParserNonExisting() {
    vfsStream::setup('modules');
    $info = $this->infoParser->parse(vfsStream::url('modules') . '/does_not_exist.info.txt');
    $this->assertTrue(empty($info), 'Non existing info.yml returns empty array.');
  }

  /**
   * Test if correct exception is thrown for a broken info file.
   *
   * @covers ::parse
   */
  public function testInfoParserBroken() {
    $broken_info = <<<BROKEN_INFO
# info.yml for testing broken YAML parsing exception handling.
name: File
type: module
description: 'Defines a file field type.'
package: Core
version: VERSION
core: 8.x
dependencies::;;
  - field
BROKEN_INFO;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'broken.info.txt' => $broken_info,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/broken.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage('broken.info.txt');
    $this->infoParser->parse($filename);
  }

  /**
   * Tests that missing required keys are detected.
   *
   * @covers ::parse
   */
  public function testInfoParserMissingKeys() {
    $missing_keys = <<<MISSINGKEYS
# info.yml for testing missing name, description, and type keys.
package: Core
version: VERSION
dependencies:
  - field
MISSINGKEYS;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'missing_keys.info.txt' => $missing_keys,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/missing_keys.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage('Missing required keys (type, name) in vfs://modules/fixtures/missing_keys.info.txt');
    $this->infoParser->parse($filename);
  }

  /**
   * Tests that missing 'core' and 'core_dependency' keys are detected.
   *
   * @covers ::parse
   */
  public function testMissingCoreCoreDependency() {
    $missing_keys = <<<MISSINGKEYS
# info.yml for testing missing name, description, and type keys.
package: Core
version: VERSION
type: module
name: Really Cool Module
dependencies:
  - field
MISSINGKEYS;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'missing_keys.info.txt' => $missing_keys,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/missing_keys.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage("The 'core' or the 'core_dependency' key must be present in vfs://modules/fixtures/missing_keys.info.txt");
    $this->infoParser->parse($filename);
  }

  /**
   * Tests that 'core' and 'core_dependency' retain their values.
   *
   * @covers ::parse
   */
  public function testCoreCoreDependency() {
    $core_and_core_dependency = <<<BOTHCORECOREDEPENDENCY
# info.yml for testing core and core_dependency keys.
package: Core
core: 8.x
core_dependency: ^8.8
version: VERSION
type: module
name: Really Cool Module
dependencies:
  - field
BOTHCORECOREDEPENDENCY;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'core_and_core_dependency.info.txt' => $core_and_core_dependency,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/core_and_core_dependency.info.txt');
    $info_values = $this->infoParser->parse($filename);
    $this->assertSame($info_values['core'], '8.x');
    $this->assertSame($info_values['core_dependency'], '^8.8');
  }

  /**
   * Tests that missing required key is detected.
   *
   * @covers ::parse
   */
  public function testInfoParserMissingKey() {
    $missing_key = <<<MISSINGKEY
# info.yml for testing missing type key.
name: File
description: 'Defines a file field type.'
package: Core
version: VERSION
core: 8.x
dependencies:
  - field
MISSINGKEY;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'missing_key.info.txt' => $missing_key,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/missing_key.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage('Missing required keys (type) in vfs://modules/fixtures/missing_key.info.txt');
    $this->infoParser->parse($filename);
  }

  /**
   * Tests that 'core_dependency' throws an exception if constraint is invalid.
   *
   * @covers ::parse
   */
  public function testCoreDependencyInvalid() {
    $core_dependency = <<<COREDEPENDENCY
# info.yml for core_dependency validation.
name: Big Forms 
description: 'Alters all forms to be a little bit bigger.'
package: Core
type: module
version: VERSION
core_dependency: '^8.7'
dependencies:
  - field
COREDEPENDENCY;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'core_dependency.info.txt' => $core_dependency,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/core_dependency.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage("The 'core_dependency' can not be used to specify compatibility specific version before 8.7.7 in vfs://modules/fixtures/core_dependency.info.txt");
    $this->infoParser->parse($filename);
  }

  /**
   * Tests common info file.
   *
   * @covers ::parse
   */
  public function testInfoParserCommonInfo() {
    $common = <<<COMMONTEST
core: 8.x
name: common_test
type: module
description: 'testing info file parsing'
simple_string: 'A simple string'
version: "VERSION"
double_colon: dummyClassName::method
COMMONTEST;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'common_test.info.txt' => $common,
      ],
    ]);
    $info_values = $this->infoParser->parse(vfsStream::url('modules/fixtures/common_test.info.txt'));
    $this->assertEquals($info_values['simple_string'], 'A simple string', 'Simple string value was parsed correctly.');
    $this->assertEquals($info_values['version'], \Drupal::VERSION, 'Constant value was parsed correctly.');
    $this->assertSame($info_values['core_dependency'], '8.x');
    $this->assertEquals($info_values['double_colon'], 'dummyClassName::method', 'Value containing double-colon was parsed correctly.');
  }

}

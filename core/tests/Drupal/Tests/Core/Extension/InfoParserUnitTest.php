<?php

namespace Drupal\Tests\Core\Extension;

use Drupal\Core\Extension\InfoParser;
use Drupal\Core\Extension\InfoParserException;
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
   * Tests that missing 'core' and 'core_version_requirement' keys are detected.
   *
   * @covers ::parse
   */
  public function testMissingCoreCoreVersionRequirement() {
    $missing_core_and_core_version_requirement = <<<MISSING_CORE_AND_CORE_VERSION_REQUIREMENT
# info.yml for testing core and core_version_requirement.
package: Core
version: VERSION
type: module
name: Skynet
dependencies:
  - self_awareness
MISSING_CORE_AND_CORE_VERSION_REQUIREMENT;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'missing_core_and_core_version_requirement.info.txt' => $missing_core_and_core_version_requirement,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/missing_core_and_core_version_requirement.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage("The 'core' or the 'core_version_requirement' key must be present in vfs://modules/fixtures/missing_core_and_core_version_requirement.info.txt");
    $this->infoParser->parse($filename);
  }

  /**
   * Tests that 'core_version_requirement: ^8.8' is valid with no 'core' key.
   *
   * @covers ::parse
   */
  public function testCoreVersionRequirement88() {
    $core_version_requirement = <<<BOTH_CORE_VERSION_REQUIREMENT
# info.yml for testing core and core_version_requirement keys.
package: Core
core_version_requirement: ^8.8
version: VERSION
type: module
name: Module for That
dependencies:
  - field
BOTH_CORE_VERSION_REQUIREMENT;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'core_version_requirement.info.txt' => $core_version_requirement,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/core_version_requirement.info.txt');
    $info_values = $this->infoParser->parse($filename);
    $this->assertSame($info_values['core_version_requirement'], '^8.8');
  }

  /**
   * Tests that 'core_version_requirement: ^8.8' is invalid with a 'core' key.
   *
   * @covers ::parse
   */
  public function testCoreCoreVersionRequirement88() {
    $core_and_core_version_requirement_88 = <<<BOTH_CORE_CORE_VERSION_REQUIREMENT_88
# info.yml for testing core and core_version_requirement keys.
package: Core
core: 8.x
core_version_requirement: ^8.8
version: VERSION
type: module
name: Form auto submitter
dependencies:
  - field
BOTH_CORE_CORE_VERSION_REQUIREMENT_88;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'core_and_core_version_requirement_88.info.txt' => $core_and_core_version_requirement_88,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/core_and_core_version_requirement_88.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage("The 'core_version_requirement' constraint (^8.8) requires the 'core' not be set in vfs://modules/fixtures/core_and_core_version_requirement_88.info.txt");
    $this->infoParser->parse($filename);
  }

  /**
   * Tests a invalid 'core' key.
   *
   * @covers ::parse
   */
  public function testInvalidCore() {
    $invalid_core = <<<INVALID_CORE
# info.yml for testing invalid core key.
package: Core
core: ^8
version: VERSION
type: module
name: Llama or Alpaca
description: Tells whether an image is of a Llama or Alpaca
dependencies:
  - llama_detector
  - alpaca_detector
INVALID_CORE;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        'invalid_core.info.txt' => $invalid_core,
      ],
    ]);
    $filename = vfsStream::url('modules/fixtures/invalid_core.info.txt');
    $this->expectException('\Drupal\Core\Extension\InfoParserException');
    $this->expectExceptionMessage("Invalid 'core' value \"^8\" in vfs://modules/fixtures/invalid_core.info.txt");
    $this->infoParser->parse($filename);
  }

  /**
   * Tests a invalid 'core_version_requirement'.
   *
   * @covers ::parse
   *
   * @dataProvider providerCoreVersionRequirementInvalid
   */
  public function testCoreVersionRequirementInvalid($file_name, $constraint) {
    $invalid_core_version_requirement = <<<INVALID_CORE_VERSION_REQUIREMENT
# info.yml for core_version_requirement validation.
name: Gracie Evaluator
description: 'Determines if Gracie is a "Good Dog". The answer is always "Yes".'
package: Core
type: module
version: VERSION
core_version_requirement: '$constraint'
dependencies:
  - goodness_api
INVALID_CORE_VERSION_REQUIREMENT;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        "$file_name.info.txt" => $invalid_core_version_requirement,
        "$file_name-duplicate.info.txt" => $invalid_core_version_requirement,
      ],
    ]);
    $exception_message = "The 'core_version_requirement' can not be used to specify compatibility specific version before 8.7.7 in vfs://modules/fixtures/";
    try {
      $this->infoParser->parse(vfsStream::url("modules/fixtures/$file_name.info.txt"));
    }
    catch (InfoParserException $exception) {
      $this->assertSame($exception_message . "$file_name.info.txt", $exception->getMessage());
      try {
        $this->infoParser->parse(vfsStream::url("modules/fixtures/$file_name-duplicate.info.txt"));
      }
      catch (InfoParserException $exception) {
        $this->assertSame($exception_message . "$file_name-duplicate.info.txt", $exception->getMessage());
        return;
      }
    }

    $this->fail('The exception was not thrown when parsing the info file the second time.');
  }

  /**
   * Dataprovider for testCoreVersionRequirementInvalid().
   */
  public function providerCoreVersionRequirementInvalid() {
    return [
      '8.0.0-alpha2' => ['alpha2', '8.0.0-alpha2'],
      '8.6.0-rc1' => ['rc1', '8.6.0-rc1'],
      '^8.7' => ['8_7', '^8.7'],
      '>8.6.3' => ['gt8_6_3', '>8.6.3'],
    ];
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
    $this->assertEquals($info_values['double_colon'], 'dummyClassName::method', 'Value containing double-colon was parsed correctly.');
    $this->assertSame('8.x', $info_values['core']);
    $this->assertFalse(isset($info_values['core_version_requirement']));
    $this->assertFalse($info_values['core_incompatible']);
  }

  /**
   * @covers ::parse
   *
   * @dataProvider providerCoreIncompatibility
   */
  public function testCoreIncompatibility($file_name, $constraint, $expected) {
    $file_name = "core_incompatible_$file_name";

    $core_incompatibility = <<<CORE_INCOMPATIBILITY
core_version_requirement: $constraint
name: common_test
type: module
description: 'testing info file parsing'
simple_string: 'A simple string'
version: "VERSION"
double_colon: dummyClassName::method
CORE_INCOMPATIBILITY;

    vfsStream::setup('modules');
    vfsStream::create([
      'fixtures' => [
        "$file_name.info.txt" => $core_incompatibility,
      ],
    ]);
    $info_values = $this->infoParser->parse(vfsStream::url("modules/fixtures/$file_name.info.txt"));
    $this->assertSame($expected, $info_values['core_incompatible']);
  }

  /**
   * Dataprovider for testCoreIncompatibility().
   */
  public function providerCoreIncompatibility() {
    list($major, $minor) = explode('.', \Drupal::VERSION);

    $next_minor = $minor + 1;
    $next_major = $major + 1;
    return [
      'next_minor' => [
        'next_minor',
        "^$major.$next_minor",
        TRUE,
      ],
      'current_major_next_major' => [
        'current_major_next_major',
        "^$major || ^$next_major",
        FALSE,
      ],
      'previous_major_next_major' => [
        'previous_major_next_major',
        "^1 || ^$next_major",
        TRUE,
      ],
      'invalid' => [
        'invalid',
        'this-string-is-invalid',
        TRUE,
      ],
      'current_minor' => [
        'current_minor',
        "~$major.$minor",
        FALSE,
      ],
    ];
  }

}

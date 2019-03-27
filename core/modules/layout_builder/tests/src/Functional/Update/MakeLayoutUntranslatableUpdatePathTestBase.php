<?php

namespace Drupal\Tests\layout_builder\Functional\Update;

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Tests the upgrade path for translatable layouts.
 *
 * @see layout_builder_post_update_make_layout_untranslatable()
 *
 * @group layout_builder
 * @group legacy
 */

class MakeLayoutUntranslatableUpdatePathTestBase extends UpdatePathTestBase {

  /**
   * @var array
   */
  protected $layout_builder_test_cases = [
    'article' => [
      'has_translation' => TRUE,
      'has_layout' => FALSE,
      'nid' => 1,
      'vid' => 2,
      'title' => 'Test Article - Spanish title',
    ],
    'page' => [
      'has_translation' => FALSE,
      'has_layout' => TRUE,
      'nid' => 4,
      'vid' => 5,
      'title' => 'Page Test - Spanish title',
    ],
  ];

  /**
   * @var array
   */
  protected $expected_bundle_updates = [
    'article' => TRUE,
    'page' => TRUE,
  ];


  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/layout-builder.php',
      __DIR__ . '/../../../fixtures/update/layout-builder-field-schema.php',
      __DIR__ . '/../../../fixtures/update/layout-builder-translation.php',
    ];
  }

  /**
   * Tests the upgrade path for translatable layouts.
   *
   * @see layout_builder_post_update_make_layout_untranslatable()
   */
  public function testDisableTranslationOnLayouts() {
    $this->runUpdates();
    foreach ($this->expected_bundle_updates as $bundle => $field_update_expected) {
      $this->assertEquals(
        $field_update_expected,
        !FieldConfig::loadByName('node', $bundle, OverridesSectionStorage::FIELD_NAME)->isTranslatable(),
        $field_update_expected ? "Field on $bundle set to be non-translatable." : "Field on $bundle not set to non-translatable."
      );
    }
  }
}

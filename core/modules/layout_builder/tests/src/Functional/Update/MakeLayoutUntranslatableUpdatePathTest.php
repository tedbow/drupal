<?php

namespace Drupal\Tests\layout_builder\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
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
class MakeLayoutUntranslatableUpdatePathTest extends UpdatePathTestBase {

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
    $expected_bundle_updates = [
      'article' => TRUE,
      'page' => TRUE,
    ];
    foreach ($expected_bundle_updates as $bundle => $field_update_expected) {
      $this->assertEquals(
        $field_update_expected,
        !FieldConfig::loadByName('node', $bundle, OverridesSectionStorage::FIELD_NAME)->isTranslatable(),
        $field_update_expected ? "Field on $bundle set to be non-translatable." : "Field on $bundle not set to non-translatable."
      );
    }
  }
}

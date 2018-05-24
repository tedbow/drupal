<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\InlineBlockType;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests that the inline block feature works correctly.
 *
 * @group layout_builder
 */
class InlineBlockContentBlockTest extends InlineBlockTestBase {

  use ContextualLinkClickTrait;

  /**
   * {@inheritdoc}
   */
  public static $inlineBlockCssLocator = '.block-inline-blockbasic';

  /**
   * {@inheritdoc}
   */
  public static $blockEntityType = 'inline_block';

  /**
   * Adds body field.
   *
   * @param string $type_id
   *   The type id.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function addBodyField($type_id) {
    // Add or remove the body field, as needed.
    $field = FieldConfig::loadByName('inline_block', $type_id, 'body');
    if (empty($field)) {
      $field_storage = FieldStorageConfig::loadByName('inline_block', 'body');
      if (empty($field_storage)) {
        $field_storage = FieldStorageConfig::create([
          'field_name' => 'body',
          'entity_type' => 'inline_block',
          'type' => 'text',
        ]);
        $field_storage->save();
      }
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $type_id,
        'label' => 'Body',
        'settings' => ['display_summary' => FALSE],
      ]);
      $field->save();

      // Assign widget settings for the 'default' form mode.
      entity_get_form_display('inline_block', $type_id, 'default')
        ->setComponent('body', [
          'type' => 'text_textarea_with_summary',
        ])
        ->save();

      // Assign display settings for 'default' view mode.
      entity_get_display('inline_block', $type_id, 'default')
        ->setComponent('body', [
          'label' => 'hidden',
          'type' => 'text_default',
        ])
        ->save();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function createBlockBundle() {
    $bundle = InlineBlockType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ]);
    $bundle->save();
    $this->addBodyField($bundle->id());
  }

}

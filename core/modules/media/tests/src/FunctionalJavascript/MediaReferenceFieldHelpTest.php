<?php

namespace Drupal\Tests\media\FunctionalJavascript;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests related to media reference fields.
 *
 * @group media
 */
class MediaReferenceFieldHelpTest extends MediaJavascriptTestBase {

  /**
   * Test our custom help texts when creating a field.
   *
   * @see media_form_field_ui_field_storage_add_form_alter()
   */
  public function testFieldCreationHelpText() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $type = $this->drupalCreateContentType([
      'type' => 'foo',
    ]);
    $this->drupalGet("/admin/structure/types/manage/{$type->id()}/fields/add-field");

    $field_types = [
      'file',
      'image',
      'field_ui:entity_reference:media',
    ];
    $description_ids = array_map(function ($item) {
      return '#edit-description-' . Html::cleanCssIdentifier($item);
    }, $field_types);

    // Choose a boolean field, none of the description containers should be
    // visible.
    $assert_session->optionExists('edit-new-storage-type', 'boolean');
    $page->selectFieldOption('edit-new-storage-type', 'boolean');
    foreach ($description_ids as $description_id) {
      $this->assertFalse($assert_session->elementExists('css', $description_id)->isVisible());
    }
    // Select each of the file, image, and media fields and verify their
    // descriptions are now visible and match the expected text.
    $help_text = 'Use Media reference fields for most files, images, audio, videos, and remote media. Use File or Image reference fields when creating your own media types, or for legacy files and images created before enabling the Media module.';
    foreach ($field_types as $field_name) {
      $assert_session->optionExists('edit-new-storage-type', $field_name);
      $page->selectFieldOption('edit-new-storage-type', $field_name);
      $field_description_element = $assert_session->elementExists('css', '#edit-description-' . Html::cleanCssIdentifier($field_name));
      $this->assertTrue($field_description_element->isVisible());
      $this->assertEquals($help_text, $field_description_element->getText());
    }
  }

  /**
   * Test our custom help texts when creating a field.
   *
   * @see media_field_widget_entity_reference_autocomplete_form_alter()
   * @see media_field_widget_multiple_entity_reference_autocomplete_form_alter()
   */
  public function testMediaAutocompleteWidgetTest() {
    $assert_session = $this->assertSession();

    $media_type1 = $this->createMediaType();
    $media_type2 = $this->createMediaType();
    $content_type = $this->createContentType();
    // The first field will have cardinality 1.
    $storage1 = FieldStorageConfig::create([
      'field_name' => 'field_card_1',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'media',
      ],
    ]);
    $storage1->save();
    $field1 = FieldConfig::create([
      'label' => 'Media (card 1)',
      'field_storage' => $storage1,
      'entity_type' => 'node',
      'bundle' => $content_type->id(),
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $media_type1->id() => $media_type1->id(),
            $media_type2->id() => $media_type2->id(),
          ],
        ],
      ],
    ]);
    $field1->save();
    entity_get_form_display('node', $content_type->id(), 'default')
      ->setComponent('field_card_1', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();

    // Do the same with a field with unlimited cardinality.
    $storage2 = FieldStorageConfig::create([
      'field_name' => 'field_card_unlimited',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'media',
      ],
    ]);
    $storage2->save();
    $field2 = FieldConfig::create([
      'label' => 'Media (card unlimited)',
      'field_storage' => $storage2,
      'entity_type' => 'node',
      'bundle' => $content_type->id(),
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $media_type1->id() => $media_type1->id(),
            $media_type2->id() => $media_type2->id(),
          ],
        ],
      ],
    ]);
    $field2->save();
    entity_get_form_display('node', $content_type->id(), 'default')
      ->setComponent('field_card_unlimited', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();

    // Check that both widgets display the expected help text on the form.
    $this->drupalGet("/node/add/{$content_type->id()}");
    // Chedk the wrapper fieldsets are present.
    $fieldset1 = $assert_session->elementExists('css', '#edit-field-card-1-wrapper fieldset');
    $fieldset2 = $assert_session->elementExists('css', '#edit-field-card-unlimited-wrapper fieldset');
    // Check some help texts are the ones we expect.
    $this->assertEquals($field1->getLabel(), $fieldset1->find('css', 'legend')->getText());
    $this->assertEquals($field2->getLabel(), $fieldset2->find('css', 'legend')->getText());
    $h4s = $fieldset1->findAll('css', 'h4');
    $this->assertEquals("Create new {$field1->getLabel()}", $h4s[0]->getText());
    $this->assertEquals("Use existing {$field1->getLabel()}", $h4s[1]->getText());
    $h4s = $fieldset2->findAll('css', 'h4');
    $this->assertEquals("Create new {$field2->getLabel()}", $h4s[0]->getText());
    // The multiple-widget has an empty h4 in our way, that's why we use the
    // next one instead.
    $this->assertEquals("Use existing {$field2->getLabel()}", $h4s[2]->getText());
    $span1 = $assert_session->elementExists('css', 'span.reuse-media-help', $fieldset1);
    $this->assertEquals("Create your media on the media add page (opens a new window), then add it by name to the field below.", $span1->getText());
    $link1 = $assert_session->elementExists('css', 'a', $span1);
    $this->assertEquals("media add page", $link1->getText());
    $this->assertEquals(Url::fromRoute('entity.media.add_page')->toString(), $link1->getAttribute('href'));
    $span2 = $assert_session->elementExists('css', 'span.reuse-media-help', $fieldset2);
    $this->assertEquals("Create your media on the media add page (opens a new window), then add it by name to the field below.", $span2->getText());
    $link2 = $assert_session->elementExists('css', 'a', $span2);
    $this->assertEquals("media add page", $link2->getText());
    $this->assertEquals(Url::fromRoute('entity.media.add_page')->toString(), $link2->getAttribute('href'));
    $description1 = $assert_session->elementExists('css', '#edit-field-card-1-0-target-id--description', $fieldset1);
    $this->assertEquals("Type part of the media name. See the media list (opens a new window) to help locate media. Allowed media types: {$media_type1->id()}, {$media_type2->id()}.", $description1->getText());
    $link3 = $assert_session->elementExists('css', 'a', $description1);
    $this->assertEquals("media list", $link3->getText());
    $this->assertEquals(Url::fromRoute('entity.media.collection')->toString(), $link3->getAttribute('href'));
    $description2 = $assert_session->elementExists('css', '#edit-field-card-unlimited-0-target-id--description', $fieldset2);
    $this->assertEquals("Type part of the media name. See the media list (opens a new window) to help locate media. Allowed media types: {$media_type1->id()}, {$media_type2->id()}.", $description2->getText());
    $link4 = $assert_session->elementExists('css', 'a', $description2);
    $this->assertEquals("media list", $link4->getText());
    $this->assertEquals(Url::fromRoute('entity.media.collection')->toString(), $link4->getAttribute('href'));

    // Create a non-media entity reference and make sure our help text is not
    // showing up.
    $storage3 = FieldStorageConfig::create([
      'field_name' => 'field_not_a_media_field',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $storage3->save();
    $field3 = FieldConfig::create([
      'label' => 'No media here!',
      'field_storage' => $storage3,
      'entity_type' => 'node',
      'bundle' => $content_type->id(),
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $content_type->id() => $content_type->id(),
          ],
        ],
      ],
    ]);
    $field3->save();
    entity_get_form_display('node', $content_type->id(), 'default')
      ->removeComponent('field_card_1')
      ->removeComponent('field_card_unlimited')
      ->setComponent('field_not_a_media_field', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    $this->drupalGet("/node/add/{$content_type->id()}");
    $assert_session->pageTextNotContains('Create your media on the media add page (opens a new window), then add it by name to the field below.');
    $assert_session->pageTextNotContains('Type part of the media name. See the media list (opens a new window) to help locate media.');
  }

}

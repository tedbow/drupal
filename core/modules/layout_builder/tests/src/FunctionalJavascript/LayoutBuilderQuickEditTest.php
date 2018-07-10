<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests that Layout Builder functions with Quick Edit.
 *
 * @covers layout_builder_entity_view_alter()
 * @covers layout_builder_quickedit_render_field()
 *
 * @group layout_builder
 */
class LayoutBuilderQuickEditTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'ckeditor',
    'contextual',
    'editor',
    'filter',
    'filter_test',
    'layout_builder',
    'node',
    'quickedit',
    'toolbar',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createContentType(['type' => 'article']);

    Editor::create([
      'editor' => 'ckeditor',
      'format' => 'filtered_html',
    ])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'access toolbar',
      'access in-place editing',
      'access content',
      'edit any article content',
      'use text format filtered_html',
    ]));
  }

  /**
   * Tests that Layout Builder functions with Quick Edit.
   */
  public function testLayoutBuilderQuickEdit() {
    $assert_session = $this->assertSession();

    // Create a test node.
    $node = $this->createNode([
      'type' => 'article',
      'title' => 'The node title',
      'body' => [
        [
          'value' => '<p>The node body</p>',
          'format' => 'filtered_html',
        ],
      ],
    ]);

    // Get the component UUID of the default body field block.
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $view_display */
    $view_display = EntityViewDisplay::collectRenderDisplay($node, 'default');
    $sections = $view_display->getRuntimeSections($node);
    $section = reset($sections);
    $component_uuid = array_keys($section->getComponents())[0];
    $field_id = 'node/' . $node->id() . '/body/' . $node->language()->getId() . '/layout_builder-0-' . $component_uuid;

    // Assemble common CSS selectors.
    $entity_selector = '[data-quickedit-entity-id="node/' . $node->id() . '"]';
    $field_selector = '[data-quickedit-field-id="' . $field_id . '"]';

    // Verify that our custom view mode is present.
    /* @see layout_builder_entity_view_alter() */
    $this->drupalGet('node/' . $node->id());
    $assert_session->elementExists('css', $field_selector);

    // Wait until Quick Edit loads.
    $condition = "jQuery('" . $entity_selector . " .quickedit').length > 0";
    $this->assertJsCondition($condition, 10000);

    // Initiate Quick Editing.
    $this->getSession()->executeScript("jQuery('.toolbar-icon-menu.is-active').click()");
    $this->click('.contextual-toolbar-tab button');
    $this->click($entity_selector . ' [data-contextual-id] > button');
    $this->click($entity_selector . ' [data-contextual-id] .quickedit > a');
    $this->click($field_selector);
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Trigger an edit with Javascript (this is a "contenteditable" element).
    $this->getSession()->executeScript("jQuery('" . $field_selector . "').text('Hello world').trigger('keyup');");

    // To prevent 403s on save, we re-set our request (cookie) state.
    $this->prepareRequest();

    // Save the change.
    $this->getSession()->executeScript("jQuery('.quickedit-button.action-save').click()");
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert that the field was re-rendered properly.
    /* @see layout_builder_quickedit_render_field() */
    $assert_session->elementExists('css', 'p:contains("Hello world")');
    $assert_session->elementExists('css', $field_selector);
  }

}

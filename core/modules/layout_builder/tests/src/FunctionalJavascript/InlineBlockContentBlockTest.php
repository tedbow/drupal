<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests that the inline block feature works correctly.
 *
 * @group layout_builder
 */
class InlineBlockContentBlockTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'layout_test',
    'block',
    'block_content',
    'node',
    'contextual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'bundle_with_section_field']);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

  /**
   * {@inheritdoc}
   */
  public function testInlineBlocks() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1/layout');

    // Add a basic block with the body field set.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue('The block body');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Save Layout');
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The block body');

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    // Click the "Configure" contextual link.
    $this->getSession()->executeScript('jQuery(".block-inline-block-contentbasic .contextual button").click()');
    $assert_session->elementExists('css', '.block-inline-block-contentbasic .contextual .layout-builder-block-update a')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertSame('The block body', $textarea->getValue());
    $textarea->setValue('The new block body!');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Save Layout');
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The new block body!');
  }

}

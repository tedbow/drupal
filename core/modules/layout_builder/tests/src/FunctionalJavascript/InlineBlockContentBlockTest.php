<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests that the inline block feature works correctly.
 *
 * @group layout_builder
 */
class InlineBlockContentBlockTest extends JavascriptTestBase {

  use ContextualLinkClickTrait;

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

    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node2 title',
      'body' => [
        [
          'value' => 'The node2 body',
        ],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testInlineBlocks() {
    $this->createBlockContentType('basic', 'Basic block');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $this->drupalGet($field_ui_prefix . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");
    // Add a basic block with the body field set.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Add new Block');
    $assert_session->assertWaitOnAjaxRequest();
    $page->findField('Title')->setValue('Block title');
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue('The DEFAULT block body');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '.block-inline-block-contentbasic', 'The DEFAULT block body');

    $this->clickLink('Save Layout');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1/layout');

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    $this->clickContextualLink('.block-inline-block-contentbasic', 'Configure');
    $textarea = $assert_session->waitForElementVisible('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $page->findField('Title')->setValue('Block title');
    $this->assertSame('The DEFAULT block body', $textarea->getValue());
    $textarea->setValue('The NEW block body!');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Save Layout');
    $assert_session->pageTextContains('The layout override has been saved.');
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body');
    $assert_session->pageTextNotContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');

    // Add a basic block with the body field set.
    $this->drupalGet('node/1/layout');
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Add new Block');
    $assert_session->assertWaitOnAjaxRequest();
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue('The 2nd block body');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Save Layout');
    $assert_session->pageTextContains('The layout override has been saved.');
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body!');
    $assert_session->pageTextContains('The 2nd block body');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');
    $assert_session->pageTextNotContains('The 2nd block body');

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    /* @var \Behat\Mink\Element\NodeElement $inline_block_2 */
    $inline_block_2 = $page->findAll('css', '.block-inline-block-contentbasic')[1];
    $uuid = $inline_block_2->getAttribute('data-layout-block-uuid');
    $this->clickContextualLink(".block-inline-block-contentbasic[data-layout-block-uuid=\"$uuid\"]", 'Configure');
    $textarea = $assert_session->waitForElementVisible('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertSame('The 2nd block body', $textarea->getValue());
    $textarea->setValue('The 2nd NEW block body!');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Save Layout');
    $assert_session->pageTextContains('The layout override has been saved.');
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body!');
    $assert_session->pageTextContains('The 2nd NEW block body!');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body!');
    $assert_session->pageTextNotContains('The 2nd NEW block body!');

    // The default layout inline block should be changed.
    $this->drupalGet("$field_ui_prefix/display-layout/default");
    $assert_session->pageTextContains('The DEFAULT block body');
    // Confirm default layout still only has 1 inline block.
    $assert_session->elementsCount('css', '.block-inline-block-contentbasic', 1);
  }

  /**
   * Tests the workflow for adding an inline block depending on number of types.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAddWorkFlow() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));

    $layout_default_path = 'admin/structure/types/manage/bundle_with_section_field/display-layout/default';
    $this->drupalGet($layout_default_path);
    // Add a basic block with the body field set.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    // Confirm that with no block content types the link does not appear.
    $assert_session->linkNotExists('Add new Block');

    $this->createBlockContentType('basic', 'Basic block');

    $this->drupalGet($layout_default_path);
    // Add a basic block with the body field set.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    // Confirm with only 1 type the "Add new Block" link goes directly to block
    // add form.
    $assert_session->linkNotExists('Basic block');
    $this->clickLink('Add new Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldExists('Title');

    $this->createBlockContentType('advanced', 'Advanced block');

    $this->drupalGet($layout_default_path);
    // Add a basic block with the body field set.
    $page->clickLink('Add Block');
    // Confirm more than 1 type exists "Add new block" shows a list block types.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkNotExists('Basic block');
    $assert_session->linkNotExists('Advanced block');
    $this->clickLink('Add new Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldNotExists('Title');
    $assert_session->linkExists('Basic block');
    $assert_session->linkExists('Advanced block');

    $this->clickLink('Advanced block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldExists('Title');
  }

  /**
   * Creates a block content type.
   *
   * @param string $id
   *   The block type id.
   * @param string $label
   *   The block type label.
   */
  protected function createBlockContentType($id, $label) {
    $bundle = BlockContentType::create([
      'id' => $id,
      'label' => $label,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

}

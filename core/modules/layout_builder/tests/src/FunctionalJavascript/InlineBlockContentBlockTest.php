<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
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
    'layout_builder_test',
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

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
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
    $this->drupalGet($field_ui_prefix . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");
    // Add a basic block with the body field set.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '.block-categories details:contains(Create new block)');
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
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
    $assert_session->elementExists('css', '.block-categories details:contains(Create new block)');
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue('2nd Block title');
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
   * Tests adding a new inline content block and then not saving the layout.
   *
   * @dataProvider layoutNoSaveProvider
   */
  public function testNoLayoutSave($operation, $no_save_link_text, $confirm_button_text) {

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
    ]));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->assertEmpty(BlockContent::loadMultiple(), 'No content blocks exist');
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');

    $this->drupalGet('node/1/layout');

    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '.block-categories details:contains(Create new block)');
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue('Block title');
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue('The block body');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('The block body');
    $this->clickLink($no_save_link_text);
    if ($confirm_button_text) {
      $page->pressButton($confirm_button_text);
    }
    $this->drupalGet('node/1');
    $this->assertEmpty(BlockContent::loadMultiple(), 'No content blocks were created when layout is canceled.');
    $assert_session->pageTextNotContains('The block body');

    $this->drupalGet('node/1/layout');

    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '.block-categories details:contains(Create new block)');
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue('Block title');
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue('The block body');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('The block body');

    $this->clickLink('Save Layout');
    $assert_session->pageTextContains('The layout override has been saved.');
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The block body');
    $blocks = BlockContent::loadMultiple();
    $this->assertEquals(count($blocks), 1);
    /* @var \Drupal\block_content\Entity\BlockContent $block */
    $block = array_pop($blocks);
    $revision_id = $block->getRevisionId();

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    $this->clickContextualLink('.block-inline-block-contentbasic', 'Configure');
    $textarea = $assert_session->waitForElementVisible('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $page->findField('Title')->setValue('Block title updated');
    $this->assertSame('The block body', $textarea->getValue());
    $textarea->setValue('The block updated body');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('The block updated body');

    $this->clickLink($no_save_link_text);
    if ($confirm_button_text) {
      $page->pressButton($confirm_button_text);
    }
    $this->drupalGet('node/1');

    $blocks = BlockContent::loadMultiple();
    // When reverting or canceling the update block should not be on the page.
    $assert_session->pageTextNotContains('The block updated body');
    if ($operation === 'cancel') {
      // When canceling the original block body should appear.
      $assert_session->pageTextContains('The block body');

      $this->assertEquals(count($blocks), 1);
      $block = array_pop($blocks);
      $this->assertEquals($block->getRevisionId(), $revision_id);
      $this->assertEquals($block->get('body')->getValue()[0]['value'], 'The block body');
    }
    else {
      // When reverting the original block body does not appear on the page.
      $assert_session->pageTextNotContains('The block body');
      // When reverting to defaults any inline blocks used only in the
      // override should be deleted.
      // @todo Add test coverage for an inline block used in a default layout
      // and an override. When reverting to defaults if the block is also in the
      // default don't delete the content block.
      // Also if the content block was first created in the default then also
      // used different override then the content block may be used any other
      // override for this bundle.
      // So the only way to be sure the content block isn't referenced in any
      // other override for this bundle you would have to check every other
      // entity for this bundle.
      // It may be better when creating an override to duplicate any content
      // blocks. Then we could safely delete any content block in an override.
      $this->assertEmpty($blocks);
    }



  }

  /**
   * Provides test data for ::testNoLayoutSave().
   */
  public function layoutNoSaveProvider() {
    return [
      'cancel' => [
        'cancel',
        'Cancel Layout',
        NULL,
      ],
      'revert' => [
        'revert',
        'Revert to defaults',
        'Revert',
      ],
    ];
  }

  /**
   * Tests inline blocks revisioning.
   */
  public function testInlineBlocksRevisioning() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'administer nodes',
      'bypass node access',
    ]));

    // Enable override.
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1/layout');

    // Add a inline block.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '.block-categories details:contains(Create new block)');
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue('Block title');
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue('The DEFAULT block body');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '.block-inline-block-contentbasic', 'The DEFAULT block body');

    $this->clickLink('Save Layout');
    $assert_session->pageTextContains('The layout override has been saved.');
    $this->drupalGet('node/1');

    $assert_session->pageTextContains('The DEFAULT block body');

    // Create a new revision.
    $this->drupalGet('node/1/edit');
    $page->pressButton('Save');

    $this->drupalGet('node/1');

    $assert_session->linkExists('Revisions');

    // Update the block.
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

    // Revert to first revision.
    $revision_url = 'node/1/revisions/1/revert';
    $this->drupalGet($revision_url);
    $page->pressButton('Revert');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');
  }

}

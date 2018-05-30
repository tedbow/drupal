<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Temporary inline-block base test.
 *
 * 2 issues are currently trying to solve the same problem of inline block
 * creation. The 2 different methods should function the same way so the
 * functional test should be nearly identical to make sure they do.
 *
 * @see https://www.drupal.org/node/2957425
 * @see https://www.drupal.org/node/2974860
 */
abstract class InlineBlockTestBase extends JavascriptTestBase {

  use ContextualLinkClickTrait;

  /**
   * CSS locator for entity blocks.
   *
   * @var string
   */
  public static $inlineBlockCssLocator;

  /**
   * The block entity type.
   *
   * @var string
   */
  public static $blockEntityType;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
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

    $this->createContentType(['type' => 'bundle_with_section_field', 'new_revision' => TRUE]);
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
    $this->createBlockBundle();
  }

  /**
   * Tests adding and editing of inline blocks.
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
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1/layout');

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('The DEFAULT block body', 'The NEW block body!');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body');
    $assert_session->pageTextNotContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');

    // Add a basic block with the body field set.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('2nd Block title', 'The 2nd block body');
    $this->assertSaveLayout();
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
    $inline_block_2 = $page->findAll('css', static::$inlineBlockCssLocator)[1];
    $uuid = $inline_block_2->getAttribute('data-layout-block-uuid');
    $block_css_locator = static::$inlineBlockCssLocator . "[data-layout-block-uuid=\"$uuid\"]";
    $this->configureInlineBlock('The 2nd block body', 'The 2nd NEW block body!', $block_css_locator);
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body!');
    $assert_session->pageTextContains('The 2nd NEW block body!');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body!');
    $assert_session->pageTextNotContains('The 2nd NEW block body!');

    // The default layout entity block should be changed.
    $this->drupalGet("$field_ui_prefix/display-layout/default");
    $assert_session->pageTextContains('The DEFAULT block body');
    // Confirm default layout still only has 1 entity block.
    $assert_session->elementsCount('css', static::$inlineBlockCssLocator, 1);
  }

  /**
   * Tests adding a new entity block and then not saving the layout.
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
    $this->assertEmpty($this->getInlineStorage()->loadMultiple(), 'No entity blocks exist');
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');

    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block title', 'The block body');
    $this->clickLink($no_save_link_text);
    if ($confirm_button_text) {
      $page->pressButton($confirm_button_text);
    }
    $this->drupalGet('node/1');
    $this->assertEmpty($this->getInlineStorage()->loadMultiple(), 'No entity blocks were created when layout is canceled.');
    $assert_session->pageTextNotContains('The block body');

    $this->drupalGet('node/1/layout');

    $this->addInlineBlockToLayout('Block title', 'The block body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The block body');
    $blocks = $this->getInlineStorage()->loadMultiple();
    $this->assertEquals(count($blocks), 1);
    /* @var \Drupal\Core\Entity\ContentEntityBase $block */
    $block = array_pop($blocks);
    $revision_id = $block->getRevisionId();

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('The block body', 'The block updated body');
    $assert_session->pageTextContains('The block updated body');

    $this->clickLink($no_save_link_text);
    if ($confirm_button_text) {
      $page->pressButton($confirm_button_text);
    }
    $this->drupalGet('node/1');

    $blocks = $this->getInlineStorage()->loadMultiple();
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
      // The block should not be visible.
      // Blocks are currently only deleted when the parent entity is deleted.
      $assert_session->pageTextNotContains('The block body');
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
   * Saves a layout and asserts the message is correct.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function assertSaveLayout() {
    $assert_session = $this->assertSession();
    $assert_session->linkExists('Save Layout');
    $this->clickLink('Save Layout');
    $this->assertNotEmpty($assert_session->waitForElement('css', '.messages--status'));
    if (stristr($this->getUrl(), 'admin/structure') === FALSE) {
      $assert_session->pageTextContains('The layout override has been saved.');
    }
    else {
      $assert_session->pageTextContains('The layout has been saved.');
    }
  }

  /**
   * Tests entity blocks revisioning.
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

    // Add a entity block.
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');

    $assert_session->pageTextContains('The DEFAULT block body');

    // Create a new revision.
    $this->drupalGet('node/1/edit');
    $page->pressButton('Save');

    $this->drupalGet('node/1');

    $assert_session->linkExists('Revisions');

    // Update the block.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('The DEFAULT block body', 'The NEW block body');
    $this->assertSaveLayout();
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

  /**
   * Tests that entity blocks deleted correctly.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testDeletion() {
    /** @var \Drupal\Core\Cron $cron */
    $cron = \Drupal::service('cron');
    $this->drupalLogin($this->drupalCreateUser([
      'administer content types',
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'administer nodes',
      'bypass node access',
    ]));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add a block to default layout.
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $this->drupalGet($field_ui_prefix . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();

    $this->assertCount(1, $this->getInlineStorage()->loadMultiple());
    $default_block_id = $this->getLatestBlockEntityId();

    // Ensure the block shows up on node pages.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');

    // Ensure we have 2 copies of the block in node overrides.
    $this->drupalGet('node/1/layout');
    $this->assertSaveLayout();
    $node_1_block_id = $this->getLatestBlockEntityId();

    $this->drupalGet('node/2/layout');
    $this->assertSaveLayout();
    $node_2_block_id = $this->getLatestBlockEntityId();
    $this->assertCount(3, $this->getInlineStorage()->loadMultiple());

    $this->drupalGet($field_ui_prefix . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");

    $this->assertNotEmpty($this->loadBlock($default_block_id));
    // Remove block from default.
    $this->removeInlineBlockFromLayout();
    $this->assertSaveLayout();
    $cron->run();
    // Ensure the block in the default was deleted.
    $this->assertEmpty($this->loadBlock($default_block_id));
    // Ensure other blocks still exist.
    $this->assertCount(2, $this->getInlineStorage()->loadMultiple());

    $this->drupalGet('node/1/layout');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Remove block from override.
    // Currently revisions are not actually created so this check will not pass.
    // @see https://www.drupal.org/node/2937199
    /*$this->removeInlineBlockFromLayout();
    $this->assertSaveLayout();
    $cron->run();
    // Ensure entity block is not deleted because it is needed in revision.
    $this->assertNotEmpty($this->loadBlock($node_1_block_id));
    $this->assertCount(2, $this->getInlineStorage()->loadMultiple());*/

    // Ensure entity block is deleted when node is deleted.
    $this->drupalGet('node/1/delete');
    $page->pressButton('Delete');
    $this->assertEmpty(Node::load(1));
    $cron->run();
    $this->assertEmpty($this->loadBlock($node_1_block_id));
    $this->assertCount(1, $this->getInlineStorage()->loadMultiple());

    // Add another block to the default.
    $this->drupalGet($field_ui_prefix . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");
    $this->addInlineBlockToLayout('Title 2', 'Body 2');
    $this->assertSaveLayout();
    $cron->run();
    $default_block2_id = $this->getLatestBlockEntityId();
    $this->assertCount(2, $this->getInlineStorage()->loadMultiple());

    // Delete the other node so bundle can be deleted.
    $this->drupalGet('node/2/delete');
    $page->pressButton('Delete');
    $this->assertEmpty(Node::load(2));
    $cron->run();
    // Ensure entity block was deleted.
    $this->assertEmpty($this->loadBlock($node_2_block_id));
    $this->assertCount(1, $this->getInlineStorage()->loadMultiple());

    // Delete the bundle which has the default layout.
    $this->drupalGet("$field_ui_prefix/delete");
    $page->pressButton('Delete');
    $cron->run();

    // Ensure the entity block in default is deleted when bundle is deleted.
    $this->assertEmpty($this->loadBlock($default_block2_id));
    $this->assertCount(0, $this->getInlineStorage()->loadMultiple());
  }

  /**
   * Gets the latest block entity id.
   */
  protected function getLatestBlockEntityId() {
    $block_ids = \Drupal::entityQuery(static::$blockEntityType)->sort('id', 'DESC')->range(0, 1)->execute();
    $block_id = array_pop($block_ids);
    $this->assertNotEmpty($this->loadBlock($block_id));
    return $block_id;
  }

  /**
   * Removes an entity block from the layout but does not save the layout.
   */
  protected function removeInlineBlockFromLayout() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $rendered_block = $page->find('css', static::$inlineBlockCssLocator)->getText();
    $this->assertNotEmpty($rendered_block);
    $assert_session->pageTextContains($rendered_block);
    $this->clickContextualLink(static::$inlineBlockCssLocator, 'Remove block');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', '#drupal-off-canvas')->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains($rendered_block);
  }

  /**
   * Adds an entity block to the layout.
   *
   * @param string $title
   *   The title field value.
   * @param string $body
   *   The body field value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function addInlineBlockToLayout($title, $body) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '.block-categories details:contains(Create new block)');
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue($title);
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue($body);
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $found_new_text = FALSE;
    /** @var \Behat\Mink\Element\NodeElement $element */
    foreach ($page->findAll('css', static::$inlineBlockCssLocator) as $element) {
      if (stristr($element->getText(), $body)) {
        $found_new_text = TRUE;
        break;
      }
    }
    $this->assertNotEmpty($found_new_text, 'Found block text on page.');
  }

  /**
   * Create block entity type bundle for test.
   */
  abstract protected function createBlockBundle();

  /**
   * Returns the entity storage for inline blocks.
   *
   * @return \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   *   The storage handler.
   */
  protected function getInlineStorage() {
    return $this->container->get('entity_type.manager')->getStorage(static::$blockEntityType);
  }

  /**
   * Loads a block entity.
   *
   * @param int $id
   *   The block entity id.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|null
   *   The block entity if exists.
   */
  protected function loadBlock($id) {
    return $this->getInlineStorage()->load($id);
  }

  /**
   * Configures an inline block in the Layout Builder.
   *
   * @param string $old_body
   *   The old body field value.
   * @param string $new_body
   *   The new body field value.
   * @param string $block_css_locator
   *   The CSS locator to use to select the contextual link.
   */
  protected function configureInlineBlock($old_body, $new_body, $block_css_locator = NULL) {
    $block_css_locator = $block_css_locator ?: static::$inlineBlockCssLocator;
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink($block_css_locator, 'Configure');
    $textarea = $assert_session->waitForElementVisible('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertSame($old_body, $textarea->getValue());
    $textarea->setValue($new_body);
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
  }

}

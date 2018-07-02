<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Base class for testing inline blocks.
 */
abstract class InlineBlockTestBase extends JavascriptTestBase {

  use ContextualLinkClickTrait;

  /**
   * Locator for inline blocks.
   */
  const INLINE_BLOCK_LOCATOR = '.block-inline-block-contentbasic';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block_content',
    'layout_builder',
    'block',
    'node',
    'contextual',
  ];

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockStorage;

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
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());

    $this->blockStorage = $this->container->get('entity_type.manager')->getStorage('block_content');
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
    try {
      $this->assertNotEmpty($assert_session->waitForElement('css', '.messages--status'));
    }
    catch (\Exception $e) {
      //file_put_contents('/Users/ted.bowman/Sites/www/no-message.html', $this->getSession()->getPage()->getOuterHtml());
      throw  $e;
    }


    if (stristr($this->getUrl(), 'admin/structure') === FALSE) {
      $assert_session->pageTextContains('The layout override has been saved.');
    }
    else {
      $assert_session->pageTextContains('The layout has been saved.');
    }
  }

  /**
   * Gets the latest block entity id.
   */
  protected function getLatestBlockEntityId() {
    $block_ids = \Drupal::entityQuery('block_content')->sort('id', 'DESC')->range(0, 1)->execute();
    $block_id = array_pop($block_ids);
    $this->assertNotEmpty($this->blockStorage->load($block_id));
    return $block_id;
  }

  /**
   * Removes an entity block from the layout but does not save the layout.
   */
  protected function removeInlineBlockFromLayout() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $rendered_block = $page->find('css', static::INLINE_BLOCK_LOCATOR)->getText();
    $this->assertNotEmpty($rendered_block);
    $assert_session->pageTextContains($rendered_block);
    $this->clickContextualLink(static::INLINE_BLOCK_LOCATOR, 'Remove block');
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
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.block-categories details:contains(Create new block)'));
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue($title);
    $textarea = $assert_session->elementExists('css', '[name="settings[block_form][body][0][value]"]');
    $textarea->setValue($body);
    $page->pressButton('Add Block');
    // @todo Replace with 'assertNoElementAfterWait()' after
    // https://www.drupal.org/project/drupal/issues/2892440.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');
    $found_new_text = FALSE;
    /** @var \Behat\Mink\Element\NodeElement $element */
    foreach ($page->findAll('css', static::INLINE_BLOCK_LOCATOR) as $element) {
      if (stristr($element->getText(), $body)) {
        $found_new_text = TRUE;
        break;
      }
    }
    try {
      $this->assertNotEmpty($found_new_text, 'Found block text on page.');
    }
    catch (\Exception $e) {
      //file_put_contents('/Users/ted.bowman/Sites/www/not-found.html', $page->getOuterHtml());
      throw $e;
    }

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
    $block_css_locator = $block_css_locator ?: static::INLINE_BLOCK_LOCATOR;
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

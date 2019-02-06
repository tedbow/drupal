<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests moving blocks via the form.
 *
 * @group layout_builder
 */
class MoveBlockFormTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

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
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'access contextual links',
    ]));

    // Enable layout builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );

    $this->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display-layout/default');

    $expected_block_order = [
      '.block-extra-field-blocknodebundle-with-section-fieldlinks',
      '.block-field-blocknodebundle-with-section-fieldbody',
    ];
    $this->assertRegionBlocksOrder(
      0,
      'content',
      $expected_block_order
    );
    // Add a top section using the Two column layout.
    $assert_session->linkExists('Add Section');
    $page->clickLink('Add Section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $assert_session->linkExists('Two column');
    $page->clickLink('Two column');
    $this->assertRegionBlocksOrder(
      1,
      'content',
      $expected_block_order
    );

    // Add a 'Powered by Drupal' block in the 'top' region of the new section.
    $top_block_locator = '[data-layout-delta="0"].layout--twocol [data-region="top"] [data-layout-block-uuid]';
    $assert_session->elementNotExists('css', $top_block_locator);
    $top_add_block = $page->find('css', '[data-layout-delta="0"].layout--twocol [data-region="top"] .new-block__link');
    $top_add_block->click();
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas a:contains("Powered by Drupal")');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Powered by Drupal');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '[data-drupal-selector="edit-actions-submit"]'));
    $page->pressButton('Add Block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', $top_block_locator));

    // Ensure the request has completed before the test starts.
    $this->waitForNoElement('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
  }

  /**
   * Tests moving a block.
   */
  public function testMoveBlock() {
    $page = $this->getSession()->getPage();

    // Reorder body field in current region.
    $this->openBodyMoveForm(1, 'content', ['Links', 'Body']);
    $this->moveBlockWithKeyboard('up', 'Body', ['Body *', 'Links']);
    $page->pressButton('Move');
    $expected_block_order = [
      '.block-field-blocknodebundle-with-section-fieldbody',
      '.block-extra-field-blocknodebundle-with-section-fieldlinks',
    ];
    $this->assertRegionBlocksOrder(
      1,
      'content',
      $expected_block_order
    );
    $this->clickLink('Save Layout');
    $this->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(
      1,
      'content',
      $expected_block_order
    );

    // Move the body block into the top region above existing block.
    $this->openBodyMoveForm(1, 'content', ['Body', 'Links']);
    $page->selectFieldOption('Region', '0:top');
    $this->assertBlockTable(['Powered by Drupal', 'Body']);
    $this->moveBlockWithKeyboard('up', 'Body', ['Body *', 'Powered by Drupal']);
    $page->pressButton('Move');
    $expected_block_order = [
      '.block-field-blocknodebundle-with-section-fieldbody',
      '.block-system-powered-by-block',
    ];
    $this->assertRegionBlocksOrder(
      0,
      'top',
      $expected_block_order
    );
    // Ensure the body block is no longer in the content region.
    $this->assertRegionBlocksOrder(
      1,
      'content',
      [
        '.block-extra-field-blocknodebundle-with-section-fieldlinks',
      ]
    );
    $this->clickLink('Save Layout');
    $this->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(
      0,
      'top',
      $expected_block_order
    );

    // Move into the bottom region that has no existing blocks.
    $this->openBodyMoveForm(0, 'top', ['Body', 'Powered by Drupal']);
    $page->selectFieldOption('Region', '0:bottom');
    $this->assertBlockTable(['Body']);
    $page->pressButton('Move');
    $this->assertRegionBlocksOrder(
      0,
      'bottom',
      [
        '.block-field-blocknodebundle-with-section-fieldbody',
      ]
    );
  }

  /**
   * Asserts the correct block labels appear in the draggable tables.
   *
   * @param string[] $expected_block_labels
   *   The expected block lables.
   */
  protected function assertBlockTable(array $expected_block_labels) {
    $page = $this->getSession()->getPage();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $block_tds = $page->findAll('css', '[data-drupal-selector="edit-components"] tr td:nth-of-type(1)');
    $this->assertCount(count($block_tds), $expected_block_labels);
    /** @var \Behat\Mink\Element\NodeElement $block_td */
    foreach ($block_tds as $block_td) {
      $this->assertSame(array_shift($expected_block_labels), trim($block_td->getText()));
    }
  }

  /**
   * Waits for an element to be removed from the page.
   *
   * @param string $selector
   *   CSS selector.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   *
   * @todo Remove in https://www.drupal.org/node/2892440.
   */
  protected function waitForNoElement($selector, $timeout = 10000) {
    $condition = "(typeof jQuery !== 'undefined' && jQuery('$selector').length === 0)";
    $this->assertJsCondition($condition, $timeout);
  }

  /**
   * Gets the body field CSS locator.
   *
   * @param int $section_delta
   *   The section delta.
   * @param string $region
   *   The region name.
   *
   * @return string
   *   The CSS locator.
   */
  protected function getBodyFieldLocator($section_delta, $region) {
    return "[data-layout-delta=\"$section_delta\"] [data-region=\"$region\"] .block-field-blocknodebundle-with-section-fieldbody";
  }

  /**
   * Moves a block in the draggable table.
   *
   * @param string $direction
   *   The direction to move the block in the table.
   * @param string $block_label
   *   The block label.
   * @param array $updated_blocks
   *   The update blocks order.
   */
  protected function moveBlockWithKeyboard($direction, $block_label, array $updated_blocks) {
    $keys = [
      'up' => 38,
      'down' => 40,
    ];
    $key = $keys[$direction];
    $handle = $this->findRowHandle($block_label);

    $handle->keyDown($key);
    $handle->keyUp($key);

    $handle->blur();
    $this->assertBlockTable($updated_blocks);
  }

  /**
   * Finds the row handle for a block in the draggable table.
   *
   * @param string $block_label
   *   The block label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row handle element.
   */
  protected function findRowHandle($block_label) {
    $page = $this->getSession()->getPage();
    $handle = $page->find('css', "[data-drupal-selector=\"edit-components\"] td:contains(\"$block_label\") a.tabledrag-handle");
    $this->assertNotEmpty($handle);
    return $handle;
  }

  /**
   * Asserts that blocks are in the correct order for a region.
   *
   * @param int $section_delta
   *   The section delta.
   * @param string $region
   *   The region.
   * @param array $block_selectors
   *   The block selectors.
   */
  protected function assertRegionBlocksOrder($section_delta, $region, array $block_selectors) {
    $page = $this->getSession()->getPage();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $region_selector = "[data-layout-delta=\"$section_delta\"] [data-region=\"$region\"]";
    $blocks = $page->findAll('css', "$region_selector [data-layout-block-uuid]");
    $this->assertNotEmpty($blocks);
    /** @var \Behat\Mink\Element\NodeElement $block */
    foreach ($blocks as $block) {
      $block_selector = array_shift($block_selectors);
      $expected_block = $page->find('css', "$region_selector $block_selector");
      $this->assertSame($expected_block->getAttribute('data-layout-block-uuid'), $block->getAttribute('data-layout-block-uuid'));
    }
    $this->assertEmpty($block_selectors);
  }

  /**
   * Open block for the body field.
   *
   * @param int $delta
   *   The section delta where the field should be.
   * @param string $region
   *   The region where the field should be.
   * @param array $initial_blocks
   *   The initial blocks that should be shown in the draggable table.
   */
  protected function openBodyMoveForm($delta, $region, array $initial_blocks) {
    $assert_session = $this->assertSession();
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickContextualLink($this->getBodyFieldLocator($delta, $region), 'Move');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'select[name="region"]'));
    $assert_session->fieldValueEquals('Region', "$delta:$region");
    $this->assertBlockTable($initial_blocks);
  }

}

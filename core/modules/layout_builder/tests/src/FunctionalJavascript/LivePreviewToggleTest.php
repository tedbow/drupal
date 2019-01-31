<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Behat\Mink\Exception\ExpectationException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests toggling and functionality of live preview and grid modes.
 *
 * @group layout_builder
 */
class LivePreviewToggleTest extends WebDriverTestBase {

  public static $modules = [
    'layout_builder',
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'bundle_for_this_particular_test']);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));
  }

  /**
   * Test live preview toggle.
   */
  public function testLivePreviewGridModeToggle() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $links_field_grid_mode_label = 'Placeholder for the "Links" field';
    $body_field_grid_mode_label = 'Placeholder for the "Body" field';
    $live_preview_body_text = 'I should only be visible if live preview is enabled.';

    $this->drupalPostForm(
      'admin/structure/types/manage/bundle_for_this_particular_test/display/default',
      ['layout[enabled]' => TRUE, 'layout[allow_custom]' => TRUE],
      'Save'
    );

    $node = $this->createNode([
      'type' => 'bundle_for_this_particular_test',
      'body' => [
        [
          'value' => $live_preview_body_text,
        ],
      ],
    ]);

    $node->save();

    // Open single item layout page.
    $this->drupalGet('node/1/layout');
    // Grid mode label should not be visible, block preview content should be.
    $assert_session->elementNotExists('css', '.data-layout-grid-mode-label');
    $assert_session->pageTextContains($live_preview_body_text);

    // Switch to grid mode, check if preview content replaced with grid labels.
    $page->uncheckField('layout-builder-live-preview');
    $assert_session->waitForElementVisible('css', '.data-layout-grid-mode-label');

    // Wait for preview content hide() to complete.
    $this->waitForNoElement('[data-layout-grid-mode-label] .field--name-body:visible');
    $assert_session->pageTextNotContains($live_preview_body_text);

    // Check that grid mode is maintained on page reload.
    $this->getSession()->reload();
    $assert_session->elementExists('css', '.data-layout-grid-mode-label');
    $assert_session->pageTextNotContains($live_preview_body_text);

    // Check that dragging to reposition blocks works in grid mode.
    $this->assertOrderInPage(['Placeholder for the "Links" field', 'Placeholder for the "Body" field']);

    $links_block_grid_child = $assert_session->elementExists('css', "[data-layout-grid-mode-label='$links_field_grid_mode_label'] h2");
    $body_block_grid_child = $assert_session->elementExists('css', "[data-layout-grid-mode-label='$body_field_grid_mode_label'] h2");
    $body_block_grid_child->dragTo($links_block_grid_child);
    $assert_session->assertWaitOnAjaxRequest();

    // Check that the drag-triggered rebuild did not take UI out of grid mode.
    $assert_session->pageTextNotContains($live_preview_body_text);
    // Check that drag successfully repositioned blocks.
    $this->assertOrderInPage(['Placeholder for the "Body" field', 'Placeholder for the "Links" field']);

    // Check if block position maintained after switching back to live preview.
    $page->checkField('layout-builder-live-preview');
    $assert_session->waitForText($live_preview_body_text);
    $assert_session->pageTextContains($live_preview_body_text);
    $this->assertOrderInPage([$live_preview_body_text, 'Placeholder for the "Links" field']);
  }

  /**
   * Asserts that several pieces of markup are in a given order in the page.
   *
   * @param string[] $items
   *   An ordered list of strings.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When any of the given string is not found.
   *
   * @todo Remove this once https://www.drupal.org/node/2817657 is committed.
   */
  protected function assertOrderInPage(array $items) {
    $session = $this->getSession();
    $text = $session->getPage()->getHtml();
    $strings = [];
    foreach ($items as $item) {
      if (($pos = strpos($text, $item)) === FALSE) {
        throw new ExpectationException("Cannot find '$item' in the page", $session->getDriver());
      }
      $strings[$pos] = $item;
    }
    ksort($strings);
    $ordered = implode(', ', array_map(function ($item) {
      return "'$item'";
    }, $items));
    $this->assertSame($items, array_values($strings), "Found strings, ordered as: $ordered.");
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

}

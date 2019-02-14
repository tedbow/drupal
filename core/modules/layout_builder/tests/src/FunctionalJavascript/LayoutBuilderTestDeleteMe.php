<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests the Layout Builder UI.
 *
 * @group layout_builder
 */
class LayoutBuilderTestDeleteMe extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'field_ui',
    'layout_builder',
    'layout_test',
    'node',
    'quickedit',
    'dblog',
  ];

  protected $profile = 'standard';

  /**
   * The node to customize with Layout Builder.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * A string used to mark the current page.
   *
   * @var string
   *
   * @todo Remove in https://www.drupal.org/project/drupal/issues/2909782.
   */
  private $pageReloadMarker;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    //$this->createContentType(['type' => 'bundle_with_section_field']);
    $this->node = $this->createNode([
      'type' => 'article',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'access in-place editing',
      'edit any article content',
      'access site reports',
    ], 'foobar'));
  }

  /**
   * Tests configurable layouts.
   */
  public function testSetupQuickEdit() {
    $layout_url = 'node/1/layout';

    \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'full',
      ])
      ->enable()
      ->setThirdPartySetting('layout_builder', 'enabled', TRUE)
      ->setThirdPartySetting('layout_builder', 'allow_custom', TRUE)
      ->save();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('node/1');
    $assert_session->waitForElementVisible('css', '.never',10000000000000);

  }

  /**
   * Waits for an element to be removed from the page.
   *
   * @param string $selector
   *   CSS selector.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   * @param string $message
   *   (optional) Custom message to display with the assertion.
   *
   * @todo: Remove after https://www.drupal.org/project/drupal/issues/2892440
   */
  public function assertNoElementAfterWait($selector, $timeout = 10000, $message = '') {
    $page = $this->getSession()->getPage();
    if ($message === '') {
      $message = "Element '$selector' was not on the page after wait.";
    }
    $this->assertTrue($page->waitFor($timeout / 1000, function () use ($page, $selector) {
      return empty($page->find('css', $selector));
    }), $message);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this in https://www.drupal.org/project/drupal/issues/2918718.
   */
  protected function clickContextualLink($selector, $link_locator, $force_visible = TRUE) {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert_session */
    $assert_session = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();
    $page->waitFor(10, function () use ($page, $selector) {
      return $page->find('css', "$selector .contextual-links");
    });
    if (count($page->findAll('css', "$selector .contextual-links")) > 1) {
      throw new \Exception('More than one contextual links found by selector');
    }

    if ($force_visible && $page->find('css', "$selector .contextual .trigger.visually-hidden")) {
      $this->toggleContextualTriggerVisibility($selector);
    }

    $link = $assert_session->elementExists('css', $selector)->findLink($link_locator);
    $this->assertNotEmpty($link);

    if (!$link->isVisible()) {
      $button = $assert_session->waitForElementVisible('css', "$selector .contextual button");
      $this->assertNotEmpty($button);
      $button->press();
      $link = $page->waitFor(10, function () use ($link) {
        return $link->isVisible() ? $link : FALSE;
      });
    }

    $link->click();

    if ($force_visible) {
      $this->toggleContextualTriggerVisibility($selector);
    }
  }

  /**
   * Enable layouts.
   *
   * @param string $path
   *   The path for the manage display page.
   * @param bool $allow_custom
   *   Whether to allow custom layouts.
   */
  private function enableLayoutsForBundle($path, $allow_custom = FALSE) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet($path);
    $page->checkField('layout[enabled]');
    if ($allow_custom) {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[name="layout[allow_custom]"]'));
      $page->checkField('layout[allow_custom]');
    }
    $page->pressButton('Save');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#edit-manage-layout'));
    $assert_session->linkExists('Manage layout');
  }

  /**
   * Opens the add block form in the off-canvas dialog.
   *
   * @param string $block_title
   *   The block title which will be the link text.
   */
  private function openAddBlockForm($block_title) {
    $assert_session = $this->assertSession();
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('named', ['link', $block_title]));
    $this->clickLink($block_title);
    $this->assertOffCanvasFormAfterWait('layout_builder_add_block');
  }

  /**
   * Waits for the specified form and returns it when available and visible.
   *
   * @param string $expected_form_id
   *   The expected form ID.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   */
  private function assertOffCanvasFormAfterWait($expected_form_id, $timeout = 10000) {
    $page = $this->getSession()->getPage();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($page->waitFor($timeout / 1000, function () use ($page, $expected_form_id) {
      // Ensure the form ID exists, is visible, and has the correct value.
      $form_id_element = $page->find('hidden_field_selector', ['hidden_field', 'form_id']);

      // Ensure the off canvas dialog is visible.
      $off_canvas = $page->find('css', '#drupal-off-canvas');
      if (!$off_canvas || !$off_canvas->isVisible()) {
        return NULL;
      }
      return $form_id_element;
    }));
  }

  /**
   * Marks the page to assist determining if the page has been reloaded.
   *
   * @todo Remove in https://www.drupal.org/project/drupal/issues/2909782.
   */
  private function markCurrentPage() {
    $this->pageReloadMarker = $this->randomMachineName();
    $this->getSession()->executeScript('document.body.appendChild(document.createTextNode("' . $this->pageReloadMarker . '"));');
  }

  /**
   * Asserts that the page has not been reloaded.
   *
   * @todo Remove in https://www.drupal.org/project/drupal/issues/2909782.
   */
  private function assertPageNotReloaded() {
    $this->assertSession()->pageTextContains($this->pageReloadMarker);
  }

}

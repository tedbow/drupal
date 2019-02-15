<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests the Layout Builder UI.
 *
 * @group layout_builder
 */
class LayoutBuilderUiTest extends WebDriverTestBase {

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
    'block_content',
    'contextual',
    'views',
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
  }

  /**
   * Tests that after removing sections reloading the page does not re-add them.
   */
  public function testReloadWithNoSections() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Remove all of the sections from the page.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default/layout');
    $page->clickLink('Remove section');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();
    // Assert that there are no sections on the page.
    $assert_session->pageTextNotContains('Remove section');
    $assert_session->pageTextNotContains('Add Block');

    // Reload the page.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default/layout');
    // Assert that there are no sections on the page.
    $assert_session->pageTextNotContains('Remove section');
    $assert_session->pageTextNotContains('Add Block');
  }

  /**
   * Tests the message indicating unsaved changes.
   */
  public function testUnsavedChangesMessage() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Make and then discard changes.
    $this->assertModifiedLayout(static::FIELD_UI_PREFIX . '/display/default/layout');
    $page->clickLink('Discard changes');
    $page->pressButton('Confirm');
    $assert_session->pageTextNotContains('You have unsaved changes.');

    // Make and then save changes.
    $this->assertModifiedLayout(static::FIELD_UI_PREFIX . '/display/default/layout');
    $page->pressButton('Save layout');
    $assert_session->pageTextNotContains('You have unsaved changes.');
  }

  /**
   * Asserts that modifying a layout works as expected.
   *
   * @param string $path
   *   The path to a Layout Builder UI page.
   */
  protected function assertModifiedLayout($path) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet($path);
    $page->clickLink('Add Section');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains('You have unsaved changes.');
    $page->clickLink('One column');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContainsOnce('You have unsaved changes.');

    // Reload the page.
    $this->drupalGet($path);
    $assert_session->pageTextContainsOnce('You have unsaved changes.');
  }

  /**
   * Tests that dialog opening elements are properly highlighted.
   */
  public function testAddHighlights() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());

    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default/layout');
    $assert_session->elementsCount('css', '.new-section', 2);
    $assert_session->elementNotExists('css', '.layout-builder-highlight');
    $page->clickLink('Add Section');
    $this->assertNotEmpty($assert_session->waitForElement('css', '#drupal-off-canvas .item-list'));

    // Highlight is present with AddSectionController.
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="section-0"]');
    $page->clickLink('Two column');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas input[type="submit"][value="Add section"]'));

    // Highlight is present with ConfigureSectionForm.
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="section-0"]');

    // Submit form to add section then confirm no element highlighted.
    $page->pressButton("Add section");
    $this->assertHighlightNotExists();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '[data-layout-delta="1"]'));
    $assert_session->elementsCount('css', '.new-block', 3);

    // Add custom block.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'a:contains("Create custom block")'));

    // Highlight is present with ChooseBlockController::build().
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="block-0-first"]');
    $page->clickLink('Create custom block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas input[value="Add Block"]'));

    // Highlight is present with ChooseBlockController::inlineBlockList().
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="block-0-first"]');
    $page->pressButton('Close');
    $this->assertHighlightNotExists();

    // Highlight should persist with all block config dialogs.
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'a:contains("Recent content")'));
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="block-0-first"]');
    $page->clickLink('Recent content');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas input[value="Add Block"]'));

    // Highlight is present with ConfigureBlockFormBase::doBuildForm.
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="block-0-first"]');
    $page->pressButton('Close');
    $this->assertHighlightNotExists();

    // Highlight is present when Configure section dialog open.
    $page->clickLink('Configure section');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="section-update-0"]');
    $page->pressButton('Close');
    $this->assertHighlightNotExists();

    // Highlight is present when Remove section dialog open.
    $page->clickLink('Remove section');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $this->assertHighlightedElement('[data-layout-builder-highlight-id="section-update-0"]');
    $page->pressButton('Close');
    $this->assertHighlightNotExists();

    // Block is highlighted when its "Configure" contextual link is clicked.
    $this->clickContextualLink('.block-field-blocknodebundle-with-section-fieldbody', 'Configure');
    $assert_session->waitForElementVisible('css', "#drupal-off-canvas");
    $this->assertHighlightedElement('.block-field-blocknodebundle-with-section-fieldbody');
    $page->pressButton('Close');
    $this->assertHighlightNotExists();

    // @todo: Remove reload after https://www.drupal.org/node/2918718 completed.
    $this->getSession()->reload();

    // Block is highlighted when its "Remove block" contextual link is clicked.
    $this->clickContextualLink('.block-field-blocknodebundle-with-section-fieldbody', 'Remove block');
    $assert_session->waitForElementVisible('css', "#drupal-off-canvas");
    $this->assertHighlightedElement('.block-field-blocknodebundle-with-section-fieldbody');
    $page->pressButton('Close');
    $this->assertHighlightNotExists();
  }

  /**
   * Confirm presence of layout-builder-highlight with specific properties.
   *
   * @param string $selector
   *   The highlighted element must also match this selector.
   */
  private function assertHighlightedElement($selector) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // There is only one highlighted element.
    $assert_session->elementsCount('css', '.layout-builder-highlight', 1);

    // The selector is also the highlighted element.
    $this->assertTrue($page->find('css', $selector)->hasClass('layout-builder-highlight'));
  }

  /**
   * Waits for dialog to close and confirms no highlights present.
   */
  private function assertHighlightNotExists() {
    $this->waitForNoElement('#drupal-off-canvas');
    $this->waitForNoElement('.layout-builder-highlight');
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

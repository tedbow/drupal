<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Layout Builder UI.
 *
 * @group layout_builder
 */
class LayoutBuilderUiTest extends WebDriverTestBase {

  use LayoutBuilderTestTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  public static $modules = [
    'layout_builder',
    'layout_builder_test',
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

    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));
  }

  /**
   * Tests the message indicating unsaved changes.
   */
  public function testUnsavedChangesMessage() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Enable layout builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );

    // Make and then discard changes.
    $this->assertModifiedLayout(static::FIELD_UI_PREFIX . '/display-layout/default');
    $page->clickLink('Discard changes');
    $page->pressButton('Confirm');
    $assert_session->pageTextNotContains('You have unsaved changes.');

    // Make and then save changes.
    $this->assertModifiedLayout(static::FIELD_UI_PREFIX . '/display-layout/default');
    $page->clickLink('Save Layout');
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
   * Tests the block list in the Layout Builder.
   */
  public function testBlockList() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $state = $this->container->get('state');
    $state->set('remove_extra_blocks', TRUE);

    // Enable layout builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
    $page->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display-layout/default');

    // Add a new block.
    $page->clickLink('Add Block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));

    // The 'User fields' category should not be moved to the top if there are no
    // 'view' configurable or extra fields.
    $this->assertRelativeCategoryOrder([
      'Content fields',
      'System',
      'User fields',
    ]);

    $this->assertBlockLinkVisible('Body');
    $this->assertBlockLinkNotVisible('Title');
    $this->assertBlockLinkNotVisible('Revision ID');
    $this->clickMore('Content fields');
    $this->assertBlockLinkVisible('Title');
    $this->assertBlockLinkVisible('Revision ID');

    $this->assertBlockLinkNotVisible('Messages');
    $this->clickBlockCategory('System');
    $this->assertEmpty($this->findCategoryMoreElement('System'));
    $this->assertBlockLinkVisible('Messages');

    // Currently User has no 'view' configurable or extra fields. Therefore it
    // will have no 'More' link and the category will be closed by default.
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickBlockCategory('User fields');
    $this->assertBlockLinkVisible('Roles');
    $this->assertEmpty($this->findCategoryMoreElement('System'));

    $state->set('remove_extra_blocks', FALSE);

    $this->drupalGet(static::FIELD_UI_PREFIX . '/display-layout/default');
    // Add a new block.
    $page->clickLink('Add Block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    // User category should now be moved to the top since the new 'Bio' field
    // is 'view' configurable.
    $this->assertRelativeCategoryOrder([
      'Content fields',
      'User fields',
      'System',
    ]);
    // Ensure new 'Bio' field is visible by default and other fields are only
    // visible after clicking 'More'.
    $this->assertBlockLinkVisible('Member for');
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickMore('User fields');
    $this->assertBlockLinkVisible('\'Member for\'');
    $this->assertBlockLinkVisible('Roles');
  }

  /**
   * Clicks the 'More' element of a category.
   *
   * @param string $category
   *   The category.
   */
  protected function clickMore($category) {
    $this->findCategoryMoreElement($category)->click();
  }

  /**
   * Finds a 'details' element for a category.
   *
   * @param string $category
   *   The category to find.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The 'details' element element if it exists, otherwise NULL.
   */
  protected function findCategoryDetails($category) {
    $all_details = $this->getSession()->getPage()->findAll('css', '#drupal-off-canvas .layout-builder-block-categories details');
    foreach ($all_details as $details) {
      $summary = $details->find('css', 'summary');
      if ($summary instanceof NodeElement && $summary->getText() === $category) {
        return $details;
      }
    }
    return NULL;
  }

  /**
   * Asserts that given categories are in the correct relative order.
   *
   * @param string[] $categories
   *   The categories in the order to check.
   */
  protected function assertRelativeCategoryOrder(array $categories) {
    $page = $this->getSession()->getPage();
    $display_categories = [];
    /** @var \Behat\Mink\Element\NodeElement $category_summary */
    foreach ($page->findAll('css', '#drupal-off-canvas summary') as $category_summary) {
      $display_categories[] = $category_summary->getText();
    }
    $this->assertSame($categories, array_values(array_intersect($display_categories, $categories)));
  }

  /**
   * Find the 'More' element for a category.
   *
   * @param string $category
   *   The category.
   *
   * @return \Behat\Mink\Element\NodeElement|mixed|null
   *   The 'More' element if it exists or NULL.
   */
  protected function findCategoryMoreElement($category) {
    $details_element = $this->findCategoryDetails($category);
    $this->assertInstanceOf(NodeElement::class, $details_element);
    return $details_element->find('css', 'summary:contains(\'More\')');
  }

}

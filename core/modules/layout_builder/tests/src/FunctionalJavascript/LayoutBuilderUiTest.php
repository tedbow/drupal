<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Layout Builder UI.
 *
 * @group layout_builder
 */
class LayoutBuilderUiTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
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
    $this->createContentType(['type' => 'bundle']);
  }

  /**
   * Tests the block list in the Layout Builder.
   */
  public function testBlockList() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle';

    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $layout_path = "$field_ui_prefix/display-layout/default";
    $assert_session->addressEquals($layout_path);

    // Add a new block.
    $assert_session->linkExists('Add Block');
    $page->clickLink('Add Block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));

    // User category should not be moved to the top if there are no 'view'
    // configurable fields.
    $this->assertRelativeCategoryOrder(['Content', 'System', 'User']);
    $this->assertBlockLinkVisible('Body');
    $this->assertBlockLinkNotVisible('Title');
    $this->assertBlockLinkNotVisible('Revision ID');
    $this->clickMore('Content');
    $this->assertBlockLinkVisible('Title');
    $this->assertBlockLinkVisible('Revision ID');

    $this->assertBlockLinkNotVisible('Messages');
    $this->clickBlockCategory('System');
    $this->assertEmpty($this->findCategoryMoreElement('System'));
    $this->assertBlockLinkVisible('Messages');

    // Currently User has no configurable fields. Therefore it will have no
    // "More+" link and the category will be closed by default.
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickBlockCategory('User');
    $this->assertEmpty($this->findCategoryMoreElement('User'));
    $this->assertBlockLinkVisible('Roles');

    // Add user field that will be 'view' configurable.
    FieldStorageConfig::create([
      'field_name' => 'bio',
      'entity_type' => 'user',
      'module' => 'text',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'bio',
      'entity_type' => 'user',
      'label' => 'Bio',
      'bundle' => 'user',
      'required' => FALSE,
    ])->save();

    $this->drupalGet($layout_path);
    // Add a new block.
    $assert_session->linkExists('Add Block');
    $page->clickLink('Add Block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    // User category should now be moved to the top since the new 'Bio' field
    // is 'view' configurable.
    $this->assertRelativeCategoryOrder(['Content', 'User', 'System']);

    // Ensure new 'Bio' field is visible by default and other fields are only
    // visible after clicking 'More+'.
    $this->assertBlockLinkVisible('Bio');
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickMore('User');
    $this->assertBlockLinkVisible('Bio');
    $this->assertBlockLinkVisible('Roles');
  }

  /**
   * Asserts that block link is visible.
   *
   * @param string $link_text
   *   The link text.
   */
  protected function assertBlockLinkNotVisible($link_text) {
    $link = $this->getSession()->getPage()->find('css', "#drupal-off-canvas a:contains('$link_text')");
    $this->assertEmpty($link->isVisible());
  }

  /**
   * Asserts that block link is not visible.
   *
   * @param string $link_text
   *   The link text.
   */
  protected function assertBlockLinkVisible($link_text) {
    $link = $this->getSession()->getPage()->find('css', "#drupal-off-canvas a:contains('$link_text')");
    $this->assertNotEmpty($link->isVisible());
  }

  /**
   * Clicks the 'More +' element of a category.
   *
   * @param string $category
   *   The category.
   */
  protected function clickMore($category) {
    $this->findCategoryMoreElement($category)->click();
  }

  /**
   * Clicks a category in the block list.
   *
   * @param string $category
   *   The category.
   */
  protected function clickBlockCategory($category) {
    $page = $this->getSession()->getPage();
    $page->find('css', "#drupal-off-canvas summary:contains('$category')")->click();
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
    $all_details = $this->getSession()->getPage()->findAll('css', "#drupal-off-canvas .block-categories details");
    foreach ($all_details as $details) {
      if ($details->find('css', "summary:contains('$category')")) {
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
    foreach ($page->findAll('css', "#drupal-off-canvas summary") as $category_summary) {
      $display_categories[] = $category_summary->getText();
    }
    $this->assertEquals($categories, array_values(array_intersect($display_categories, $categories)));
  }

  /**
   * Find the 'More+' element for a category.
   *
   * @param string $category
   *   The category.
   *
   * @return \Behat\Mink\Element\NodeElement|mixed|null
   *   The 'More+' element if it exists or NULL.
   */
  protected function findCategoryMoreElement($category) {
    return $this->findCategoryDetails($category)->find('css', "summary:contains('More+')");
  }

}

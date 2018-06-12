<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests the Layout Builder UI.
 *
 * @group layout_builder
 */
class LayoutBuilderUiTest extends JavascriptTestBase {

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
    //$this->enableTheme('classy');
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
    $this->assertBlockLinkVisible('Body');
    $this->assertBlockLinkNotVisible('Title');
    $this->assertBlockLinkNotVisible('Revision ID');
    $this->clickMore('Content');
    $this->assertBlockLinkVisible('Title');
    $this->assertBlockLinkVisible('Revision ID');

    // Currently User has no configurable fields. Therefore it will have no
    // "More+" link.
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickBlockCategory('User');
    $this->assertBlockLinkVisible('Roles');

    // Add user field
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
    file_put_contents('/Users/ted.bowman/Sites/www/what.html', $page->getOuterHtml());
    // Currently User has no configurable fields. Therefore it will have no
    // "More+" link.
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickBlockCategory('User');
    $this->assertBlockLinkVisible('Bio');
    $this->assertBlockLinkNotVisible('Roles');
    $this->clickMore('User');


    $this->assertBlockLinkNotVisible('Messages');
    $this->clickBlockCategory('System');
    $this->assertBlockLinkVisible('Messages');


  }

  protected function assertBlockLinkNotVisible($link_text) {
    $link = $this->getSession()->getPage()->find('css', "#drupal-off-canvas a:contains('$link_text')");
    $this->assertEmpty($link->isVisible());
  }

  protected function assertBlockLinkVisible($link_text) {
    $link = $this->getSession()->getPage()->find('css', "#drupal-off-canvas a:contains('$link_text')");
    $this->assertNotEmpty($link->isVisible());
  }

  protected function clickMore($category) {
    $page = $this->getSession()->getPage();
    $category_details = $this->findCategoryDetails($category);
    $this->assertNotEmpty($category_details);
    $category_details->find('css', "summary:contains('More+')")->click();
  }

  /**
   * @param string $category
   */
  protected function clickBlockCategory($category) {
    $page = $this->getSession()->getPage();
    $page->find('css', "#drupal-off-canvas summary:contains('$category')")->click();
  }

  protected function findCategoryDetails($category) {
    $all_details = $this->getSession()->getPage()->findAll('css', "#drupal-off-canvas .block-categories details");
    foreach ($all_details as $details) {
      if ($details->find('css', "summary:contains('$category')")) {
        return $details;
      }
    }
    return NULL;
  }

}

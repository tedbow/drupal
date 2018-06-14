<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests the Layout Builder UI Javascript functionality.
 *
 * @group layout_builder
 */
class LayoutBuilderTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
    'node',
    'search',
    'filter',
    'filter_test',
    'block_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create two nodes.
    $this->createContentType(['type' => 'bundle_with_section_field']);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          // Create a link that should be disabled in the Layout Builder.
          'value' => '<a href="/search/node">Take me away</a>',
          'format' => 'full_html',
        ],
      ],
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The second node title',
      'body' => [
        [
          'value' => 'The second node body',
          'format' => 'full_html',
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

    BlockContent::create([
      'type' => 'basic',
      'info' => 'Block with link',
      'body' => [
        // Create a link that should be disabled in the Layout Builder.
        'value' => '<a href="/search/node">Take me away</a>',
        'format' => 'full_html',
      ],
    ]);
  }

  public function testFormsLinksDisabled() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'search content',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");
    $this->addBlock('Search form', '.search-block-form');

    $page->pressButton('Search');
    // Ensure we didn't get redirected to the search page.
    $this->assertEmpty($assert_session->waitForElement('css', '.search-form'));
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");

    $this->addBlock('Block with link', 'block-field-blockblock-contentbasicbody');


    $assert_session->linkExists('Take me away');
    $page->clickLink('Take me away');
    // Ensure we didn't get redirected to the search page.
    $this->assertEmpty($assert_session->waitForElement('css', '.search-form'));
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");

    $this->clickLink('Save Layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default");

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1');

    // Remove the section from the defaults.
    $assert_session->linkExists('Layout');
    $this->clickLink('Layout');

    // Wait for block form to be rendered in the Layout Builder.
    $this->assertNotEmpty($assert_session->waitForElement('css', '.search-block-form'));
    file_put_contents('/Users/ted.bowman/Sites/www/test.html', $page->getOuterHtml());
    $assert_session->linkExists('Take me away');
    // Ensure we didn't get redirected to the search page.
    $this->assertEmpty($assert_session->waitForElement('css', '.search-form'));
    $assert_session->addressEquals("node/1/layout");
  }

  /**
   * @param $assert_session
   * @param $page
   */
  protected function addBlock($block_link_test, $rendered_locator) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Add a new block.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $assert_session->linkExists($block_link_test);
    $this->clickLink($block_link_test);
    // Wait for off-cavnas dialog to reopen with block form.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".layout-builder-add-block"));
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add Block');

    // Wait for block form to be rendered in the Layout Builder.
    $this->assertNotEmpty($assert_session->waitForElement('css', $rendered_locator));
  }

}

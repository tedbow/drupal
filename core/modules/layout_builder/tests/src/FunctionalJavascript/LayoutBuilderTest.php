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

    $this->createContentType(['type' => 'bundle_with_section_field']);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'Node body',
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
    ])->save();
  }

  /**
   * Tests that forms and links are disabled in the Layout Builder preview.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testFormsLinksDisabled() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'search content',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    $this->drupalGet("$field_ui_prefix/display-layout/default");

    // Add a block with a link and one with a form.
    $this->addBlock('Block with link', '.block-field-blockblock-contentbasicbody');
    $this->addBlock('Search form', '.search-block-form');

    // Ensure the links and forms are disable using the defaults.
    $this->assertLinksFormDisabled();

    $this->clickLink('Save Layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default");

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1/layout');

    // Wait for search form to be rendered in the Layout Builder.
    $this->assertNotEmpty($assert_session->waitForElement('css', '.search-block-form'));
    // Ensure the links and forms are also disabled in using the override.
    $this->assertLinksFormDisabled();
  }

  /**
   * Adds a block in the Layout Builder.
   *
   * @param string $block_link_text
   *   The link text to add the block.
   * @param string $rendered_locator
   *   The CSS locator to confirm the block was rendered.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function addBlock($block_link_text, $rendered_locator) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Add a new block.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');

    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->linkExists($block_link_text);
    $this->clickLink($block_link_text);
    // Wait for off-canvas dialog to reopen with block form.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".layout-builder-add-block"));
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add Block');

    // Wait for block form to be rendered in the Layout Builder.
    $this->assertNotEmpty($assert_session->waitForElement('css', $rendered_locator));
  }

  /**
   * Asserts that forms and links added inside the Layout Builder are disabled.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertLinksFormDisabled() {
    $address = $this->getUrl();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->pressButton('Search');
    // Ensure we didn't get redirected to the search page.
    $this->assertEmpty($assert_session->waitForElement('css', '.search-form'));
    $assert_session->addressEquals($address);

    $assert_session->linkExists('Take me away');
    $page->clickLink('Take me away');
    // Ensure we didn't get redirected to the search page.
    $this->assertEmpty($assert_session->waitForElement('css', '.search-form'));
    $assert_session->addressEquals($address);
  }

}

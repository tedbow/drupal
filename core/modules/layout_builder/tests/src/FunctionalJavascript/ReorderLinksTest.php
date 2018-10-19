<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests blocks reorder links in the Layout Builder.
 *
 * @group layout_builder
 */
class ReorderLinksTest extends WebDriverTestBase {

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

    // Enable layout builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );

    $page = $this->getSession()->getPage();
    $assertSession = $this->assertSession();
    $page->clickLink('Manage layout');
    $page->clickLink('Remove section');
    $remove_button = $assertSession->waitForElementVisible('css', '#drupal-off-canvas input[value="Remove"]');
    $this->assertNotEmpty($remove_button);
    $remove_button->press();
    // Ensure no section remains.
    $this->waitForNoElement('[data-layout-delta]');

    // Add sections to test.
    $this->addSectionAtBottom('One column', '.layout--onecol');
    $this->addSectionAtBottom('Two column', '.layout--twocol');

    // Add Blocks in sections.
    $this->addBlockInSectionRegion('Block 1', 0, 'content');
    $this->addBlockInSectionRegion('Block 2', 0, 'content');
    $this->addBlockInSectionRegion('Block 3', 1, 'top');
    $this->addBlockInSectionRegion('Block 4', 1, 'second');
    $this->addBlockInSectionRegion('Block 5', 1, 'second');
    $this->addBlockInSectionRegion('Block 6', 1, 'bottom');
    $this->addBlockInSectionRegion('Block 7', 1, 'bottom');
  }

  /**
   * Tests using the reorder links.
   */
  public function testUseReorderLinks() {
    $expected_blocks = [
      0 => [
        'content' => [
          'Block 1',
          'Block 2',
        ],
      ],
      1 => [
        'top' => [
          'Block 3',
        ],
        'first' => [],
        'second' => [
          'Block 4',
          'Block 5',
        ],
        'bottom' => [
          'Block 6',
          'Block 7',
        ],
      ],
    ];
    $this->assertBlocksOrder($expected_blocks);


    $this->reorderBlock('Block 1', 'next');
    $expected_blocks = [
      0 => [
        'content' => [
          'Block 2',
          'Block 1',
        ],
      ],
      1 => [
        'top' => [
          'Block 3',
        ],
        'first' => [],
        'second' => [
          'Block 4',
          'Block 5',
        ],
        'bottom' => [
          'Block 6',
          'Block 7',
        ],
      ],
    ];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[0]['content'] = ['Block 2'];
    $expected_blocks[1]['top'] = ['Block 1', 'Block 3'];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['top'] = ['Block 3', 'Block 1'];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['top'] = ['Block 3'];
    $expected_blocks[1]['first'] = ['Block 1'];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['first'] = [];
    $expected_blocks[1]['second'] = [
      'Block 1',
      'Block 4',
      'Block 5',
    ];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['second'] = [
      'Block 4',
      'Block 1',
      'Block 5',
    ];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['second'] = [
      'Block 4',
      'Block 5',
      'Block 1',
    ];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['second'] = [
      'Block 4',
      'Block 5',
    ];
    $expected_blocks[1]['bottom'] = [
      'Block 1',
      'Block 6',
      'Block 7',
    ];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['bottom'] = [
      'Block 6',
      'Block 1',
      'Block 7',
    ];
    $this->assertBlocksOrder($expected_blocks);

    $this->reorderBlock('Block 1', 'next');
    $expected_blocks[1]['bottom'] = [
      'Block 6',
      'Block 7',
      'Block 1',
    ];
    $this->assertBlocksOrder($expected_blocks);

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
   * Adds a new section at the bottom of the page.
   *
   * @param string $layout_label
   *   The layout label.
   * @param string $assert_selector
   *   The CSS selector to assert existings after the section is added.
   */
  protected function addSectionAtBottom($layout_label, $assert_selector) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $add_section_links = $page->findAll('css', '.new-section__link');
    $add_section_link = array_pop($add_section_links);
    $add_section_link->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas .layout-selection'));
    $page->clickLink($layout_label);
    $assert_session->assertWaitOnAjaxRequest();
    $this->waitForNoElement('#drupal-off-canvas');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', "#layout-builder $assert_selector"));
  }

  /**
   * Adds a block in section region.
   *
   * @param string $block_label
   *   The block label to use.
   * @param int $section_delta
   *   The section delta.
   * @param string $region
   *   The region.
   */
  protected function addBlockInSectionRegion($block_label, $section_delta, $region) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $region_selector = $this->getRegionSelector($section_delta, $region);
    $add_block_selector = "$region_selector .new-block a";
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', $add_block_selector));
    $page->find('css', $add_block_selector)->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas .block-categories'));
    $page->clickLink('Powered by Drupal');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas .layout-builder-add-block'));
    $page->checkField('Display title');
    $page->fillField('Title', $block_label);
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->waitForNoElement('#drupal-off-canvas');

    $this->assertNotEmpty($assert_session->waitForElementVisible('css', "#layout-builder $region_selector:contains('$block_label')"));

  }

  /**
   * Reorders a block using a reorder link.
   *
   * @param string $block_label
   *   The label of the block to reorder.
   * @param string $direction
   *   The direction to move the block.
   */
  protected function reorderBlock($block_label, $direction) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $blocks = $page->findAll('css', "[data-layout-block-uuid]:contains('$block_label')");
    $this->assertCount(1, $blocks);
    /** @var \Behat\Mink\Element\NodeElement $block */
    $block = array_pop($blocks);
    $link = $block->find('css', "[data-direction_focus=\"$direction\"]");
    $this->assertNotEmpty($link);
    $link->click();
    $assert_session->assertWaitOnAjaxRequest();
  }

  /**
   * @param array $expected_blocks
   *   Nested array of expected blocks.
   *   - Level 1 keys are section deltas
   *   - Level 2 keys are regions and values are an array block labels.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertBlocksOrder(array $expected_blocks) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    foreach ($expected_blocks as $section_delta => $regions_blocks) {
      foreach ($regions_blocks as $region => $expected_block_labels) {
        $region_selector = $this->getRegionSelector($section_delta, $region);
        $assert_session->elementExists('css', $region_selector);
        $block_headings = $page->findAll('css', "$region_selector [data-layout-block-uuid] h2");
        $actual_labels = array_map(function (NodeElement $heading) {
          return $heading->getText();
        }, $block_headings);
        $this->assertSame($expected_block_labels, $actual_labels, "Blocks label match for section:$section_delta, region:$region");
      }
    }
  }

  /**
   * Gets the region CSS selector.
   *
   * @param int $section_delta
   *   The section delta.
   * @param string $region
   *   The region.
   *
   * @return string
   *   The region CSS selector.
   */
  protected function getRegionSelector($section_delta, $region) {
    return "[data-layout-delta=\"$section_delta\"] [data-region=\"$region\"]";
  }

}

<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests block_content functionality with Layout Builder.
 *
 * @group layout_builder
 */
class LayoutBuilderBlockContentTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
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
    // Create 2 custom block types.
    BlockContentType::create([
      'id' => 'example_block_type',
      'label' => 'An example block type',
      'revision' => FALSE,
    ])->save();
    BlockContentType::create([
      'id' => 'second_example_block_type',
      'label' => 'A second example block type',
      'revision' => FALSE,
    ])->save();
  }

  /**
   * Test prevention of block_content placement on itself in Layout Builder.
   */
  public function testRecursionPrevention() {
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer blocks',
      'administer block_content display',
    ]));
    $assert_session = $this->assertSession();
    $new_block_type_one = BlockContent::create([
      'type' => 'example_block_type',
      'info' => 'Example custom block',
    ])->save();
    BlockContent::create([
      'type' => 'example_block_type',
      'info' => 'Second example block',
    ])->save();
    BlockContent::create([
      'type' => 'second_example_block_type',
      'info' => 'A different block type',
    ])->save();
    // Go into manage display of block.
    $basic_block_layout_page = 'admin/structure/block/block-content/manage/example_block_type/display-layout/default';
    $this->drupalGet($basic_block_layout_page);
    // Add a new block.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    // Verify both example_block_type instances are not available to place.
    $assert_session->pageTextNotContains('Example custom block');
    $assert_session->pageTextNotContains('Second example block');
    // Verify second_example_block_type instance is available to place.
    $assert_session->pageTextContains('A different block type');

    // Allow blocks to have per-instance layouts.
    $this->drupalGet("/admin/structure/block/block-content/manage/example_block_type/display");
    $this->drupalPostForm("/admin/structure/block/block-content/manage/example_block_type/display", ['layout[allow_custom]' => TRUE], 'Save');

    // Edit first example_block_type layout.
    $this->drupalGet("block/" . $new_block_type_one . "/layout");
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    // Verify current example_block_type is not available to place.
    $assert_session->pageTextNotContains('Example custom block');
    // Verify the other example_block_type is available to place.
    $assert_session->pageTextContains('Second example block');
    // Verify second_example_block_type instance is available to place.
    $assert_session->pageTextContains('A different block type');
  }

}

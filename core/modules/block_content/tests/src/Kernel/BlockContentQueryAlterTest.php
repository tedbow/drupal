<?php

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that block content queries are not altered.
 *
 * @see block_content_query_entity_reference_alter()
 *
 * @group block_content
 */
class BlockContentQueryAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'system',
    'user',
  ];

  /**
   * The block content storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContentStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('system', ['sequence']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');

    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'spiffy',
      'label' => 'Mucho spiffy',
      'description' => "Provides a block type that increases your site's spiffiness by up to 11%",
    ]);
    $block_content_type->save();
    $this->blockContentStorage = $this->container->get('entity_type.manager')->getStorage('block_content');

    // And reusable block content entities.
    $block_reusable = BlockContent::create([
      'info' => 'Reusable Block',
      'type' => 'spiffy',
    ]);
    $block_reusable->save();
    $block__non_reusable = BlockContent::create([
      'info' => 'Non-reusable Block',
      'type' => 'spiffy',
      'reusable' => FALSE,
    ]);
    $block__non_reusable->save();
  }

  /**
   * Tests to make sure queries without the expected tags are not altered.
   *
   * @see block_content_query_entity_reference_alter()
   */
  public function testQueriesNotAltered() {
    // Ensure that queries without all the tags are not altered.
    $query = $this->blockContentStorage->getQuery();
    $this->assertCount(2, $query->execute());

    $query = $this->blockContentStorage->getQuery();
    $query->addTag('block_content_access');
    $this->assertCount(2, $query->execute());

    $query = $this->blockContentStorage->getQuery();
    $query->addTag('entity_query_block_content');
    $this->assertCount(2, $query->execute());
  }

}

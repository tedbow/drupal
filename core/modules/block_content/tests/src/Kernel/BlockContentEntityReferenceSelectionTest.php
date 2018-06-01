<?php

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests EntityReference selection handlers don't return non-reusable blocks.
 *
 * @see block_content_query_block_content_access_alter()
 *
 * @group block_content
 */
class BlockContentEntityReferenceSelectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'block_content', 'system', 'user'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('system', ['sequence']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');

    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'spiffy',
      'label' => 'Mucho spiffy',
      'description' => "Provides a block type that increases your site's spiffiness by up to 11%",
    ]);
    $block_content_type->save();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests that non-reusable blocks are not referenceable entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function testReferenceableEntities() {
    // And reusable and non-reusable block content entities.
    $block_content_reusable = BlockContent::create([
      'info' => 'Reusable Block',
      'type' => 'spiffy',
      'reusable' => TRUE,
    ]);
    $block_content_reusable->save();
    $block_content_nonreusable = BlockContent::create([
      'info' => 'Non-reusable Block',
      'type' => 'spiffy',
      'reusable' => FALSE,
    ]);
    $block_content_nonreusable->save();

    // Ensure that queries without all the tags are not altered.
    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $this->assertCount(2, $query->execute());

    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $query->addTag('block_content_access');
    $this->assertCount(2, $query->execute());

    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $query->addTag('entity_query_block_content');
    $this->assertCount(2, $query->execute());

    // Use \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection
    // class to test that getReferenceableEntities() does not get the
    // non-reusable entity.
    $configuration = [
      'target_type' => 'block_content',
      'target_bundles' => ['spiffy' => 'spiffy'],
      'sort' => ['field' => '_none'],
    ];
    $selection_handler = new DefaultSelection($configuration, '', '', $this->container->get('entity.manager'), $this->container->get('module_handler'), \Drupal::currentUser());
    $this->assertEquals(
      [
        'spiffy' => [$block_content_reusable->id() => $block_content_reusable->label()],
      ],
      $selection_handler->getReferenceableEntities()
    );
    $block_content_nonreusable->setReusable(TRUE);
    $block_content_nonreusable->save();
    // Ensure that the block is now returned as a referenceable entity.
    $this->assertEquals(
      [
        'spiffy' => [
          $block_content_reusable->id() => $block_content_reusable->label(),
          $block_content_nonreusable->id() => $block_content_nonreusable->label(),
        ],
      ],
      $selection_handler->getReferenceableEntities()
    );
  }

}

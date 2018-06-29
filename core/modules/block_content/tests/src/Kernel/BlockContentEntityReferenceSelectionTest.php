<?php

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\block_content_test\Plugin\EntityReferenceSelection\TestSelection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests EntityReference selection handlers don't return blocks with parents.
 *
 * @see block_content_query_block_content_access_alter()
 *
 * @group block_content
 */
class BlockContentEntityReferenceSelectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'block_content_test',
    'system',
    'user',
  ];

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
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests that blocks with parent are not referenceable entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function testReferenceableEntities() {
    $user = User::create([
      'name' => 'username',
      'status' => 1,
    ]);
    $user->save();

    // And block content entities with and without parents.
    $block_content = BlockContent::create([
      'info' => 'Block no parent',
      'type' => 'spiffy',
    ]);
    $block_content->save();
    $block_content_with_parent = BlockContent::create([
      'info' => 'Block with parent',
      'type' => 'spiffy',
    ]);
    $block_content_with_parent->setParentEntity($user);
    $block_content_with_parent->save();

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
    // entity wth a parent.
    $configuration = [
      'target_type' => 'block_content',
      'target_bundles' => ['spiffy' => 'spiffy'],
      'sort' => ['field' => '_none'],
    ];
    $selection_handler = new TestSelection($configuration, '', '', $this->container->get('entity.manager'), $this->container->get('module_handler'), \Drupal::currentUser());
    // Setup the 3 expectation cases.
    $both_blocks = [
      'spiffy' => [
        $block_content->id() => $block_content->label(),
        $block_content_with_parent->id() => $block_content_with_parent->label(),
      ],
    ];
    $block_no_parent = ['spiffy' => [$block_content->id() => $block_content->label()]];
    $block_with_parent = ['spiffy' => [$block_content_with_parent->id() => $block_content_with_parent->label()]];

    $this->assertEquals(
      $block_no_parent,
      $selection_handler->getReferenceableEntities()
    );

    // Test various ways in which an EntityReferenceSelection plugin could set
    // a condition on either the 'parent_entity_id' or 'parent_entity_type'
    // fields. If the plugin has set a condition on either of these fields
    // then 'block_content_query_entity_reference_alter()' will not set
    // a parent condition.
    foreach (['parent_entity_id', 'parent_entity_type'] as $field) {
      $selection_handler->setTestMode("{$field}_condition_false");
      $this->assertEquals(
        $block_no_parent,
        $selection_handler->getReferenceableEntities()
      );

      $selection_handler->setTestMode("{$field}_condition_group_false");
      $this->assertEquals(
        $block_no_parent,
        $selection_handler->getReferenceableEntities()
      );

      $selection_handler->setTestMode("{$field}_condition_group_true");
      $this->assertEquals(
        $block_with_parent,
        $selection_handler->getReferenceableEntities()
      );

      $selection_handler->setTestMode("{$field}_condition_nested_group_false");
      $this->assertEquals(
        $block_no_parent,
        $selection_handler->getReferenceableEntities()
      );

      $selection_handler->setTestMode("{$field}_condition_nested_group_true");
      $this->assertEquals(
        $block_with_parent,
        $selection_handler->getReferenceableEntities()
      );
    }

    $block_content_with_parent->removeParentEntity();
    $block_content_with_parent->save();
    // Don't use any conditions.
    $selection_handler->setTestMode(NULL);
    // Ensure that the block is now returned as a referenceable entity.
    $this->assertEquals(
      $both_blocks,
      $selection_handler->getReferenceableEntities()
    );
  }

}

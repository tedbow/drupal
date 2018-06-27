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
   * Test user to use as block parent.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $parentUser;

  /**
   * Test block without parent.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $blockWithoutParent;

  /**
   * Test block with parent.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $blockContentWithParent;

  /**
   * Test selection handler.
   *
   * @var \Drupal\block_content_test\Plugin\EntityReferenceSelection\TestSelection
   */
  protected $selectionHandler;

  /**
   * Test block expectations.
   *
   * @var array
   */
  protected $expectations;

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

    $this->parentUser = User::create([
      'name' => 'username',
      'status' => 1,
    ]);
    $this->parentUser->save();

    // And block content entities with and without parents.
    $this->blockWithoutParent = BlockContent::create([
      'info' => 'Block no parent',
      'type' => 'spiffy',
    ]);
    $this->blockWithoutParent->save();
    $this->blockContentWithParent = BlockContent::create([
      'info' => 'Block with parent',
      'type' => 'spiffy',
    ]);
    $this->blockContentWithParent->setParentEntity($this->parentUser);
    $this->blockContentWithParent->save();

    $configuration = [
      'target_type' => 'block_content',
      'target_bundles' => ['spiffy' => 'spiffy'],
      'sort' => ['field' => '_none'],
    ];
    $this->selectionHandler = new TestSelection($configuration, '', '', $this->container->get('entity.manager'), $this->container->get('module_handler'), \Drupal::currentUser());

    // Setup the 3 expectation cases.
    $this->expectations = [
      'both_blocks' => [
        'spiffy' => [
          $this->blockWithoutParent->id() => $this->blockWithoutParent->label(),
          $this->blockContentWithParent->id() => $this->blockContentWithParent->label(),
        ],
      ],
      'block_no_parent' => ['spiffy' => [$this->blockWithoutParent->id() => $this->blockWithoutParent->label()]],
      'block_with_parent' => ['spiffy' => [$this->blockContentWithParent->id() => $this->blockContentWithParent->label()]],
    ];
  }

  /**
   * Tests to make sure queries without the expected tags are not altered.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testQueriesNotAltered() {
    // Ensure that queries without all the tags are not altered.
    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $this->assertCount(2, $query->execute());

    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $query->addTag('block_content_access');
    $this->assertCount(2, $query->execute());

    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $query->addTag('entity_query_block_content');
    $this->assertCount(2, $query->execute());
  }

  /**
   * Test with no conditions set.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNoConditions() {
    $this->assertEquals(
      $this->expectations['block_no_parent'],
      $this->selectionHandler->getReferenceableEntities()
    );

    $this->blockContentWithParent->removeParentEntity();
    $this->blockContentWithParent->save();

    // Ensure that the block is now returned as a referenceable entity.
    $this->assertEquals(
      $this->expectations['both_blocks'],
      $this->selectionHandler->getReferenceableEntities()
    );
  }

  /**
   * Tests setting conditions on different levels and parent entity fields.
   *
   * @dataProvider fieldConditionProvider
   *
   * @throws \Exception
   */
  public function testFieldConditions($field, $condition_type, $has_parent) {
    $this->selectionHandler->setTestMode($field, $condition_type, $has_parent);
    $this->assertEquals(
      $has_parent ? $this->expectations['block_with_parent'] : $this->expectations['block_no_parent'],
      $this->selectionHandler->getReferenceableEntities()
    );
  }

  /**
   * Provides possible fields and condition types.
   */
  public function fieldConditionProvider() {
    $cases = [];
    foreach (['parent_entity_id', 'parent_entity_type', 'parent_status'] as $field) {
      foreach (['base', 'group', 'nested_group'] as $condition_type) {
        foreach ([TRUE, FALSE] as $has_parent) {
          $cases["$field:$condition_type:$has_parent"] = [
            $field,
            $condition_type,
            $has_parent,
          ];
        }
      }
    }
    return $cases;
  }

}

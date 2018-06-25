<?php

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests that 'block_content' entities are delete if their parent is deleted.
 */
class BlockContentParentDeleteTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'system',
    'config_test',
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
    $this->installSchema('user', ['users_data']);
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
   * Tests deleting a parent entity deletes the 'block_content' entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testDeleteParent() {
    $block_storage = $this->entityTypeManager->getStorage('block_content');

    // Test a parent entity type with a datatable.
    $user = User::create([
      'name' => 'The users',
      'mail' => 'yo@yo.com',
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

    $user->delete();

    $block_storage->resetCache([$block_content->id(), $block_content_with_parent->id()]);
    $this->assertNotEmpty($block_storage->load($block_content->id()));
    $this->assertNotEmpty($block_storage->load($block_content_with_parent->id()));

    $this->container->get('cron')->run();

    $block_storage->resetCache([$block_content->id(), $block_content_with_parent->id()]);
    $this->assertNotEmpty($block_storage->load($block_content->id()));
    $this->assertEmpty($block_storage->load($block_content_with_parent->id()));

    // Test a parent entity type without a datatable.
    $config_entity = $this->entityTypeManager->getStorage('config_test')->create([
      'id' => 'test_entity',
      'label' => 'Test config entity',
    ]);
    $config_entity->save();

    $block_content_with_parent = BlockContent::create([
      'info' => 'Block with parent',
      'type' => 'spiffy',
    ]);
    $block_content_with_parent->setParentEntity($config_entity);
    $block_content_with_parent->save();

    $config_entity->delete();

    $block_storage->resetCache([$block_content->id(), $block_content_with_parent->id()]);
    $this->assertNotEmpty($block_storage->load($block_content->id()));
    $this->assertNotEmpty($block_storage->load($block_content_with_parent->id()));

    $this->container->get('cron')->run();

    $block_storage->resetCache([$block_content->id(), $block_content_with_parent->id()]);
    $this->assertNotEmpty($block_storage->load($block_content->id()));
    $this->assertEmpty($block_storage->load($block_content_with_parent->id()));
  }

}

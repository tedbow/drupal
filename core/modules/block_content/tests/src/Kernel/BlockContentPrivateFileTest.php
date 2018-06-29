<?php

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests access to private files on blocks.
 *
 * @group block_content
 */
class BlockContentPrivateFileTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'block_content', 'system', 'user', 'file'];

  /**
   * Directory where the sample files are stored.
   *
   * @var string
   */
  protected $directory;

  /**
   * Created file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $file;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('system', ['sequence']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('block_content');

    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'type_with_file',
      'label' => 'File holder',
      'description' => "Provides a block type that has a private file",
    ]);
    $block_content_type->save();

    FieldStorageConfig::create([
      'field_name' => 'file_test',
      'entity_type' => 'block_content',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    $this->directory = $this->getRandomGenerator()->name(8);
    FieldConfig::create([
      'entity_type' => 'block_content',
      'field_name' => 'file_test',
      'bundle' => 'block_content',
      'settings' => ['file_directory' => $this->directory],
    ])->save();
    file_put_contents('private://example.txt', $this->randomMachineName());
    $this->file = File::create([
      'uri' => 'private://example.txt',
    ]);
    $this->file->save();
  }

  /**
   * Tests deleting a block_content updates the discovered block plugin.
   */
  public function testDeletingBlockContentShouldClearPluginCache() {

    // And a block content entity.
    $block_content = BlockContent::create([
      'info' => 'Spiffy prototype',
      'type' => 'type_with_file',
    ]);
    $block_content->save();

    // Make sure the block content provides a derivative block plugin in the
    // block repository.
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = $this->container->get('plugin.manager.block');
    $plugin_id = 'block_content' . PluginBase::DERIVATIVE_SEPARATOR . $block_content->uuid();
    $this->assertTrue($block_manager->hasDefinition($plugin_id));

    // Now delete the block content entity.
    $block_content->delete();
    // The plugin should no longer exist.
    $this->assertFalse($block_manager->hasDefinition($plugin_id));
  }

}

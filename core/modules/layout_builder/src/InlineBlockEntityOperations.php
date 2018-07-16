<?php

namespace Drupal\layout_builder;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for reacting to entity events related to Inline Blocks.
 *
 * @internal
 */
class InlineBlockEntityOperations implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * Inline block content usage tracking service.
   *
   * @var \Drupal\layout_builder\InlineBlockContentUsage
   */
  protected $usage;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new  EntityOperations object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\layout_builder\InlineBlockContentUsage $usage
   *   Inline block content usage tracking service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, InlineBlockContentUsage $usage, Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->storage = $entityTypeManager->getStorage('block_content');
    $this->usage = $usage;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('inline_block_content.usage'),
      $container->get('database')
    );
  }

  /**
   * Remove all unused entities on save.
   *
   * Entities that were used in prevision revisions will be removed if not
   * saving a new revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function removeUnusedForEntityOnSave(EntityInterface $entity) {
    // If the entity is new or '$entity->original' is not set then there will
    // not be any unused inline blocks to remove.
    if ($entity->isNew() || !isset($entity->original)) {
      return;
    }
    $sections = $this->getEntitySections($entity);
    // If this is a layout override and there are no sections then it is a new
    // override.
    if ($this->isEntityUsingFieldOverride($entity) && empty($sections)) {
      return;
    }
    // If this is a revisionable entity then do not remove block_content
    // entities. They could be referenced in previous revisions even if this is
    // not a new revision.
    if ($entity instanceof RevisionableInterface) {
      return;
    }
    // Delete and remove the usage for inline blocks that were removed.
    if ($removed_block_ids = $this->getRemovedBlockIds($entity)) {
      $this->deleteBlocksAndUsage($removed_block_ids);
    }
  }

  /**
   * Gets the IDs of the block content entities that were removed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout entity.
   *
   * @return int[]
   *   The block content IDs that were removed.
   */
  protected function getRemovedBlockIds(EntityInterface $entity) {
    $original_sections = $this->getEntitySections($entity->original);
    $sections = $this->getEntitySections($entity);
    $current_block_content_revision_ids = $this->getInlineBlockRevisionIdsInSections($sections);
    $original_block_content_revision_ids = $this->getInlineBlockRevisionIdsInSections($original_sections);
    // If there are any revisions in the original that aren't current there may
    // some blocks that need to be removed.
    if ($unused_original_revision_ids = array_diff($original_block_content_revision_ids, $current_block_content_revision_ids)) {
      $current_block_content_ids = $this->getBlockIdsForRevisionIds($current_block_content_revision_ids);
      $original_block_content_ids = $this->getBlockIdsForRevisionIds($unused_original_revision_ids);
      return array_diff($original_block_content_ids, $current_block_content_ids);
    }
    return [];
  }

  /**
   * Handles entity tracking on deleting a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   */
  public function handleEntityDelete(EntityInterface $entity) {
    if ($this->isLayoutCompatibleEntity($entity)) {
      $this->usage->removeByLayoutEntity($entity);
    }
  }

  /**
   * Handles saving a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function handlePreSave(EntityInterface $entity) {
    if (!$this->isLayoutCompatibleEntity($entity)) {
      return;
    }
    $duplicate_blocks = FALSE;

    if ($sections = $this->getEntitySections($entity)) {
      if ($this->isEntityUsingFieldOverride($entity)) {
        if (!$entity->isNew() && isset($entity->original)) {
          /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $original_sections_field */
          $original_sections_field = $entity->original->get('layout_builder__layout');
          if ($original_sections_field->isEmpty()) {
            // If there were no sections in the original
            // 'layout_builder__layout' field then this is a new override from a
            // default and the blocks need to be duplicated.
            $duplicate_blocks = TRUE;
          }
        }
      }
      $new_revision = FALSE;
      if ($entity instanceof RevisionableInterface) {
        // If the parent entity will have a new revision create a new revision
        // of the block.
        // @todo Currently revisions are not actually created.
        // @see https://www.drupal.org/node/2937199
        // To bypass this always make a revision because the parent entity is
        // instance of RevisionableInterface. After the issue is fixed only
        // create a new revision if '$entity->isNewRevision()'.
        $new_revision = TRUE;
      }

      foreach ($this->getInlineBlockComponents($sections) as $component) {
        /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
        $plugin = $component->getPlugin();
        $pre_save_configuration = $plugin->getConfiguration();
        $plugin->saveBlockContent($new_revision, $duplicate_blocks);
        $post_save_configuration = $plugin->getConfiguration();
        if ($duplicate_blocks || (empty($pre_save_configuration['block_revision_id']) && !empty($post_save_configuration['block_revision_id']))) {
          $this->usage->addUsage($this->getPluginBlockId($plugin), $entity->getEntityTypeId(), $entity->id());
        }
        $component->setConfiguration($post_save_configuration);
      }
    }
    $this->removeUnusedForEntityOnSave($entity);
  }

  /**
   * Gets a block ID for a inline block content plugin.
   *
   * @param \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $block_plugin
   *   The inline block plugin.
   *
   * @return int
   *   The block content ID or null none available.
   */
  protected function getPluginBlockId(InlineBlockContentBlock $block_plugin) {
    $configuration = $block_plugin->getConfiguration();
    if (!empty($configuration['block_revision_id'])) {
      $query = $this->storage->getQuery();
      $query->condition('revision_id', $configuration['block_revision_id']);
      return array_values($query->execute())[0];
    }
    return NULL;
  }

  /**
   * Delete the content blocks and delete the usage records.
   *
   * @param int[] $block_content_ids
   *   The block content entity IDs.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteBlocksAndUsage(array $block_content_ids) {
    foreach ($block_content_ids as $block_content_id) {
      if ($block = $this->storage->load($block_content_id)) {
        $block->delete();
      }
    }
    $this->usage->deleteUsage($block_content_ids);
  }

  /**
   * Removes unused block content entities.
   *
   * @param int $limit
   *   The maximum number of block content entities to remove.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeUnused($limit = 100) {
    $this->deleteBlocksAndUsage($this->usage->getUnused($limit));
  }

  /**
   * Gets blocks IDs for an array of revision IDs.
   *
   * @param int[] $revision_ids
   *   The revision IDs.
   *
   * @return int[]
   *   The block IDs.
   */
  protected function getBlockIdsForRevisionIds(array $revision_ids) {
    if ($revision_ids) {
      $query = $this->storage->getQuery();
      $query->condition('revision_id', $revision_ids, 'IN');
      $block_ids = $query->execute();
      return $block_ids;
    }
    return [];
  }

  /**
   * Saves an inline block component.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity with the layout.
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component with a inline block.
   * @param bool $new_revision
   *   Whether a new revision of the block should be created.
   * @param bool $duplicate_blocks
   *   Whether the blocks should be duplicated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveInlineBlockComponent(EntityInterface $entity, SectionComponent $component, $new_revision, $duplicate_blocks) {
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
    $plugin = $component->getPlugin();
    $pre_save_configuration = $plugin->getConfiguration();
    $plugin->saveBlockContent($new_revision, $duplicate_blocks);
    $post_save_configuration = $plugin->getConfiguration();
    if ($duplicate_blocks || (empty($pre_save_configuration['block_revision_id']) && !empty($post_save_configuration['block_revision_id']))) {
      $this->usage->addUsage($this->getPluginBlockId($plugin), $entity->getEntityTypeId(), $entity->id());
    }
    $component->setConfiguration($post_save_configuration);
  }

}

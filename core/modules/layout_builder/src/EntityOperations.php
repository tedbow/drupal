<?php

namespace Drupal\layout_builder;

use Drupal\block_content\BlockContentInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for reacting to entity events.
 *
 * @internal
 */
class EntityOperations implements ContainerInjectionInterface {

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
    if ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout') && empty($sections)) {
      return;
    }
    // If this is a revisionable entity then do not remove block_content
    // entities. They could be referenced in previous revisions even if this is
    // not a new revision.
    if ($entity instanceof RevisionableInterface) {
      return;
    }
    $original_sections = $this->getEntitySections($entity->original);
    $current_revision_ids = $this->getInBlockRevisionIdsInSection($sections);
    // If there are any revisions in the original that aren't current there may
    // some blocks that need to be removed.
    if ($original_revision_ids = array_diff($this->getInBlockRevisionIdsInSection($original_sections), $current_revision_ids)) {
      if ($removed_ids = array_diff($this->getBlockIdsForRevisionIds($original_revision_ids), $this->getBlockIdsForRevisionIds($current_revision_ids))) {
        foreach ($removed_ids as $block_content_id) {
          if ($block = $this->storage->load($block_content_id)) {
            $block->delete();
          }
        }
      }
    }
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
   * Gets the sections for an entity if any.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\layout_builder\Section[]|null
   *   The entity layout sections if available.
   *
   * @internal
   */
  protected function getEntitySections(EntityInterface $entity) {
    if ($entity->getEntityTypeId() === 'entity_view_display' && $entity instanceof LayoutBuilderEntityViewDisplay) {
      return $entity->getSections();
    }
    elseif ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout')) {
      return $entity->get('layout_builder__layout')->getSections();
    }
    return NULL;
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
      if ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout')) {
        if (!$entity->isNew() && isset($entity->original)) {
          /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $original_sections_field */
          $original_sections_field = $entity->original->get('layout_builder__layout');
          if ($original_sections_field->isEmpty()) {
            // @todo Is there a better way to tell if Layout Override is new?
            // what if is overridden and all sections removed. Currently if you
            // remove all sections from an override it reverts to the default.
            // Is that a feature or a bug?
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
        $component->setConfiguration($plugin->getConfiguration());
      }
    }
    $this->removeUnusedForEntityOnSave($entity);
  }

  /**
   * Gets components that have Inline Block plugins.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   The layout sections.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   The components that contain Inline Block plugins.
   */
  protected function getInlineBlockComponents(array $sections) {
    $inline_components = [];
    foreach ($sections as $section) {
      $components = $section->getComponents();

      foreach ($components as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof InlineBlockContentBlock) {
          $inline_components[] = $component;
        }
      }
    }
    return $inline_components;
  }

  /**
   * Determines if an entity can have a layout.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity can have a layout otherwise FALSE.
   */
  protected function isLayoutCompatibleEntity(EntityInterface $entity) {
    return ($entity->getEntityTypeId() === 'entity_view_display' && $entity instanceof LayoutBuilderEntityViewDisplay) ||
      ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout'));
  }

  /**
   * Gets a block ID for a inline block content plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The inline block content plugin.
   *
   * @return int
   *   The block content ID or null none available.
   */
  protected function getPluginBlockId(PluginInspectionInterface $plugin) {
    /** @var \Drupal\Component\Plugin\ConfigurablePluginInterface $plugin */
    $configuration = $plugin->getConfiguration();
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
   * Gets revision IDs for layout sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   The layout sections.
   *
   * @return int[]
   *   The revision IDs.
   */
  protected function getInBlockRevisionIdsInSection(array $sections) {
    $revision_ids = [];
    foreach ($this->getInlineBlockComponents($sections) as $component) {
      $configuration = $component->getPlugin()->getConfiguration();
      if (!empty($configuration['block_revision_id'])) {
        $revision_ids[] = $configuration['block_revision_id'];
      }
    }
    return $revision_ids;
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
   * Prepares a non-reusable block content entity for access checking.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The block content entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function handleAccessPrep(BlockContentInterface $block_content) {
    if (!$block_content->isReusable() && empty($block_content->getAccessDependency())) {
      /** @var \Drupal\layout_builder\InlineBlockContentUsage $usage */
      if (($layout_entity_info = $this->usage->getUsage($block_content->id()))) {
        $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
        $layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id);
        if ($this->isLayoutCompatibleEntity($layout_entity)) {
          if (!$layout_entity->getEntityType()->isRevisionable()) {
            $block_content->setAccessDependency($layout_entity);
            return;
          }
          else {
            foreach ($this->getLayoutEntityBlockContentRevisionIds($layout_entity) as $revision_id) {
              $revision = $layout_entity_storage->loadRevision($revision_id);
              $block_revision_ids = $this->getInBlockRevisionIdsInSection($this->getEntitySections($revision));
              if (in_array($block_content->getRevisionId(), $block_revision_ids)) {
                $block_content->setAccessDependency($revision);
                return;
              }
            }
          }
        }
      }
    }
  }

  /**
   * Gets the block_content entity revision IDs for layout entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $layout_entity
   *   The entity with the layout.
   *
   * @return int[]
   *   The revision IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLayoutEntityBlockContentRevisionIds(EntityInterface $layout_entity) {
    $entity_type = $this->entityTypeManager->getDefinition($layout_entity->getEntityTypeId());
    if ($revision_table = $entity_type->getRevisionTable()) {
      $query = $this->database->select($revision_table);
      $query->condition($entity_type->getKey('id'), $layout_entity->id());
      $query->fields($revision_table, [$entity_type->getKey('revision')]);
      $query->orderBy($entity_type->getKey('revision'), 'DESC');
      return $query->execute()->fetchCol();
    }
    return [];
  }

}

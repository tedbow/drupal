<?php

namespace Drupal\layout_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Database\Database;
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
   * Constructs a new  EntityOperations object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    if ($entityTypeManager->hasDefinition('block_content')) {
      $this->storage = $entityTypeManager->getStorage('block_content');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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
    // If this a new revision do not remove content_block entities.
    if ($entity instanceof RevisionableInterface && $entity->isNewRevision()) {
      return;
    }
    $original_sections = $this->getEntitySections($entity->original);
    $current_revision_ids = $this->getInBlockRevisionIdsInSection($sections);
    // If there are any revisions in the original that aren't current there may
    // some blocks that need to be removed.
    if ($original_revision_ids = array_diff($this->getInBlockRevisionIdsInSection($original_sections), $current_revision_ids)) {
      if ($removed_ids = array_diff($this->getBlockIdsForRevisionIds($original_revision_ids), $this->getBlockIdsForRevisionIds($current_revision_ids))) {
        $this->deleteBlocks($removed_ids);
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
    if ($this->isStorageAvailable() && $this->isLayoutCompatibleEntity($entity)) {
      $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
      if (!$entity_type->getDataTable()) {
        // If the entity type does not have a data table we cannot find unused
        // blocks on cron.
        $block_storage = $this->entityTypeManager->getStorage('block_content');
        $query = $block_storage->getQuery();
        $query->condition('parent_entity_id', $entity->id());
        $query->condition('parent_entity_type', $entity->getEntityTypeId());
        $block_ids = $query->execute();
        foreach ($block_ids as $block_id) {
          /** @var \Drupal\block_content\BlockContentInterface $block */
          $block = $block_storage->load($block_id);
          $block->set('parent_entity_id', NULL);
          $block->save();
        }
      }
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
    if (!$this->isStorageAvailable() || !$this->isLayoutCompatibleEntity($entity)) {
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
        $plugin->saveBlockContent($entity, $new_revision, $duplicate_blocks);
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
  protected function deleteBlocks(array $block_content_ids) {
    foreach ($block_content_ids as $block_content_id) {
      if ($block = $this->storage->load($block_content_id)) {
        $block->delete();
      }
    }
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
    if ($this->isStorageAvailable()) {
      $this->deleteBlocks($this->getUnused($limit));
    }
  }

  /**
   * The block_content entity storage is available.
   *
   * If the 'block_content' module is not enable this the public methods on this
   * class should not execute their operations.
   *
   * @return bool
   *   Whether the 'block_content' storage is available.
   */
  protected function isStorageAvailable() {
    return !empty($this->storage);
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
   * Get unused IDs of blocks.
   *
   * @param int $limit
   *   The limit of block IDs to return.
   *
   * @return int[]
   *   The block IDs.
   */
  protected function getUnused($limit) {
    $block_type_definition = $this->entityTypeManager->getDefinition('block_content');
    $data_table = $block_type_definition->getDataTable();
    $query = Database::getConnection()->select($data_table);
    $query->distinct(TRUE);
    $query->isNotNull('parent_entity_type');
    $query->fields($data_table, ['parent_entity_type']);
    $parent_entity_types = $query->execute()->fetchCol();
    $block_id_key = $block_type_definition->getKey('id');
    $block_ids = [];
    foreach ($parent_entity_types as $parent_entity_type) {
      $parent_type_definition = $this->entityTypeManager->getDefinition($parent_entity_type);
      if ($parent_data_table = $parent_type_definition->getDataTable()) {
        $sub_query = Database::getConnection()->select($parent_data_table, 'parent');
        $parent_id_key = $parent_type_definition->getKey('id');
        $sub_query->fields('parent', [$parent_id_key]);
        $sub_query->where("blocks.parent_entity_id = parent.$parent_id_key");

        $query = Database::getConnection()->select($data_table, 'blocks');
        $query->fields('blocks', [$block_id_key]);
        $query->isNotNull('parent_entity_id');
        $query->condition('parent_entity_type', $parent_entity_type);
        $query->notExists($sub_query);
        $query->range(0, $limit - count($block_ids));
        $new_block_ids = $query->execute()->fetchCol();
      }
      else {
        // @todo Handle parent types with no data table.
        $block_query = $this->entityTypeManager->getStorage('block_content')->getQuery();
        $block_query->condition('parent_entity_type', $parent_entity_type);
        $block_query->notExists('parent_entity_id');
        $block_query->range(0, $limit - count($block_ids));
        $new_block_ids = $block_query->execute();
      }
      $block_ids = array_merge($block_ids, $new_block_ids);
      if (count($block_ids) > 50) {
        break;
      }
    }
    return $block_ids;
  }

}

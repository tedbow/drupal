<?php

namespace Drupal\layout_builder;

use Drupal\block\BlockInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock;

/**
 * Service class to track Inline Blocks in Layouts.
 */
class InlineBlockContentUsage {

  /**
   * The entity usage service.
   *
   * @var \Drupal\layout_builder\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * InlineBlockContentUsage constructor.
   *
   * @param \Drupal\layout_builder\EntityUsageInterface $entity_usage
   *   The entity usage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(EntityUsageInterface $entity_usage, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityUsage = $entity_usage;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Remove all unused entities on save.
   *
   * Entities that were used in prevision revisions will be used.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function removeUnusedForEntityOnSave(EntityInterface $entity) {
    if ($this->isLayoutCompatibleEntity($entity)) {
      $sections = $this->getEntitySections($entity);
      if ($entity->isNew() || !isset($entity->original) || $sections === NULL || ($entity->getEntityTypeId() !== 'entity_view_display' && empty($sections))) {
        return;
      }
      // If this a new revision do not remove content_block entities.
      if ($entity instanceof RevisionableInterface && $entity->isNewRevision()) {
        return;
      }
      $original_sections = $this->getEntitySections($entity->original);
      $removed_ids = array_diff($this->getInlineBlockIdsInSections($original_sections), $this->getInlineBlockIdsInSections($sections));

      foreach ($removed_ids as $removed_id) {
        $this->entityUsage->remove('block_content', $removed_id, $entity->getEntityTypeId(), $entity->id());
      }
    }

  }

  /**
   * Handles entity tracking on deleting a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handleEntityDelete(EntityInterface $entity) {
    if ($this->isInlineBlockContentBlock($entity)) {
      /** @var \Drupal\block\BlockInterface $entity */
      $this->deleteBlockContentOnBlockDelete($entity);
      return;
    }
    if ($this->isLayoutCompatibleEntity($entity)) {
      $this->entityUsage->removeByUser('block_content', $entity);
    }
  }

  /**
   * Gets the sections for an entity if any.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array|\Drupal\layout_builder\Section[]|null
   *   The entity layout sections if available.
   *
   * @internal
   */
  protected function getEntitySections(EntityInterface $entity) {
    if ($entity->getEntityTypeId() === 'entity_view_display' && $entity instanceof LayoutBuilderEntityViewDisplay) {
      return $entity->getSections();
    }
    elseif ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout')) {
      /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $sections_field */
      $sections_field = $entity->get('layout_builder__layout');
      return $sections_field->getSections();
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
   */
  public function handlePreSave(EntityInterface $entity) {
    $duplicate_blocks = FALSE;

    $this->removeUnusedForEntityOnSave($entity);
    /** @var \Drupal\layout_builder\Section[] $sections */
    $sections = NULL;

    if ($this->isInlineBlockContentBlock($entity)) {
      /** @var \Drupal\block\BlockInterface $entity */
      /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
      $plugin = $entity->getPlugin();
      $plugin->saveBlockContent(FALSE, FALSE, $entity);
      $entity->set('settings', $plugin->getConfiguration());
    }
    if ($this->isLayoutCompatibleEntity($entity) && $sections = $this->getEntitySections($entity)) {
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
        // Currently revisions are not actually created.
        // @see https://www.drupal.org/node/2937199
        $new_revision = $entity->isNewRevision();
      }

      foreach ($this->getInlineBlockComponents($sections) as $component) {
        /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
        $plugin = $component->getPlugin();
        $plugin->saveBlockContent($new_revision, $duplicate_blocks, $entity);
        $component->setConfiguration($plugin->getConfiguration());
      }
    }
  }

  /**
   * The all Inline Blocks in Sections.
   *
   * @param array $sections
   *   The layout sections.
   *
   * @return int[]
   *   The block ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getInlineBlockIdsInSections(array $sections) {
    $block_ids = [];
    $components = $this->getInlineBlockComponents($sections);
    foreach ($components as $component) {
      /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
      $plugin = $component->getPlugin();
      $configuration = $plugin->getConfiguration();
      if (!empty($configuration['block_revision_id'])) {
        if ($block = $this->entityTypeManager->getStorage('block_content')
          ->loadRevision($configuration['block_revision_id'])) {
          $block_ids[] = $block->id();
        }
      }
    }
    return $block_ids;
  }

  /**
   * Deletes an Inline Block for a block entity.
   *
   * @param \Drupal\block\BlockInterface $block_entity
   *   The block entity that has an Inline Block plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteBlockContentOnBlockDelete(BlockInterface $block_entity) {
    $blockPlugin = $block_entity->getPlugin();
    $configuration = $blockPlugin->getConfiguration();
    if (!empty($configuration['block_revision_id'])) {
      /** @var \Drupal\block_content\BlockContentInterface $block_content */
      if ($block_content = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($configuration['block_revision_id'])) {
        $block_content->delete();
        $this->entityUsage->removeByUser('block_content', $block_entity, FALSE);
      }
    }
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
   * Removes all unused.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function removeAllUnused() {
    $entity_ids = $this->entityUsage->getEntitiesWithNoUses('block_content');
    foreach ($entity_ids as $entity_id) {
      if ($block = $this->entityTypeManager->getStorage('block_content')->load($entity_id)) {
        $block->delete();
      }
      $this->entityUsage->delete('block_content', $entity_id);
    }
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
   * Determines if the entity is Block entity that contains an Inline Block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity can is a block entity with an Inline Block otherwise
   *   FALSE.
   */
  protected function isInlineBlockContentBlock(EntityInterface $entity) {
    if ($entity instanceof BlockInterface) {
      $plugin = $entity->getPlugin();
      if ($plugin instanceof InlineBlockContentBlock) {
        return TRUE;
      }
    }
    return FALSE;
  }

}

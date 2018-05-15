<?php


namespace Drupal\layout_builder;


use Drupal\block\BlockInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock;

class InlineBlockContentUsage {

  /**
   * @var \Drupal\layout_builder\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * InlineBlockContentUsage constructor.
   */
  public function __construct(EntityUsageInterface $entity_usage, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityUsage = $entity_usage;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function removeUnusedForEntity(EntityInterface $entity) {
    $sections = $this->getEntitySections($entity);
    if ($entity->isNew() || !isset($entity->original) || $sections === NULL || ($entity->getEntityTypeId() !== 'entity_view_display' && empty($sections))) {
      return;
    }
    // If this a new revision do not remove content_block entities.
    if ($entity instanceof RevisionableInterface && $entity->isNewRevision()) {
      return;
    }
    $original_sections = $this->getEntitySections($entity->original);
    $removed_ids = array_diff($this->getBlockIds($original_sections), $this->getBlockIds($sections));

    foreach ($removed_ids as $removed_id) {
      $this->entityUsage->remove('block_content', $removed_id, $entity->getEntityTypeId(), $entity->id());
    }
  }


  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handleEntityDelete(EntityInterface $entity) {
    if ($this->isInlineBlockContentBlock($entity)) {
      /** @var \Drupal\block\BlockInterface $entity */
      $this->deleteBlockContentOnBlockDelete($entity->getPlugin(), $entity);
      return;
    }
    if ($this->isLayoutCompatiableEntity($entity)) {
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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handlePreSave(EntityInterface $entity) {
    $duplicate_blocks = FALSE;

    $this->removeUnusedForEntity($entity);
    /** @var \Drupal\layout_builder\Section[] $sections */
    $sections = NULL;

    if ($this->isInlineBlockContentBlock($entity)) {
      /** @var \Drupal\block\BlockInterface $entity */
      /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
      $plugin = $entity->getPlugin();
      $plugin->saveBlockContent(FALSE, FALSE, $entity);
      $entity->set('settings', $plugin->getConfiguration());
    }
    if ($entity instanceof BlockInterface) {
      $plugin = $entity->getPlugin();
      if ($plugin instanceof InlineBlockContentBlock) {
        $plugin->saveBlockContent(FALSE, FALSE, $entity);
        $entity->set('settings', $plugin->getConfiguration());
      }
    }
    if ($sections = $this->getEntitySections($entity)) {
      $inline_block_components = $this->getComponents($sections);
      if ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout')) {
        if (!$entity->isNew() && isset($entity->original)) {
          /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $original_sections_field */
          $original_sections_field = $entity->original->get('layout_builder__layout');
          if ($original_sections_field->isEmpty()) {
            // @todo Is there a better way to tell if Layout Override is new?
            // what if is overridden and all sections removed.
            $duplicate_blocks = TRUE;
          }
        }
      }
      $new_revision = FALSE;
      if ($entity instanceof RevisionableInterface) {
        // If the parent entity will have a new revision create a new revision
        // of the block.
        $new_revision = $entity->isNewRevision();
      }

      foreach ($inline_block_components as $component) {
        /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
        $plugin = $component->getPlugin();
        $plugin->saveBlockContent($new_revision, $duplicate_blocks, $entity);
        $component->setConfiguration($plugin->getConfiguration());
      }
    }
  }


  /**
   * @param array $sections
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getBlockIds(array $sections) {
    $block_ids = [];
    $components = $this->getComponents($sections);
    foreach ($components as $component) {
      /** @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $plugin */
      $plugin = $component->getPlugin();
      $configuration = $plugin->getConfiguration();
      if (!empty($configuration['block_revision_id'])) {
        if ($block = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id'])) {
          $block_ids[] = $block->id();
        }
      }
    }
    return $block_ids;
  }

  /**
   * @param \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock $blockPlugin
   * @param \Drupal\block\BlockInterface $blockEntity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteBlockContentOnBlockDelete(InlineBlockContentBlock $blockPlugin, BlockInterface $blockEntity) {
    $configuration = $blockPlugin->getConfiguration();
    if (!empty($configuration['block_revision_id'])) {
      /** @var \Drupal\block_content\BlockContentInterface $block_content */
      if ($block_content = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($configuration['block_revision_id'])) {
        $block_content->delete();
        /** @var \Drupal\layout_builder\EntityUsageInterface $entity_usage */
        $entity_usage = \Drupal::service('entity.usage');
        $entity_usage->removeByUser('block_content', $blockEntity, FALSE);
      }
    }
  }

  /**
   * @param \Drupal\layout_builder\Section[] $sections
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   */
  protected function getComponents(array $sections) {
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
        // @todo Add delete. should remove/delete be different.
        // $this->entityUsage->delete();
      }
    }
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  protected function isLayoutCompatiableEntity(EntityInterface $entity) {
    return ($entity->getEntityTypeId() === 'entity_view_display' && $entity instanceof LayoutBuilderEntityViewDisplay) ||
      ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout'));
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
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

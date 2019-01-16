<?php

namespace Drupal\layout_builder;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Methods to help with entities using the layout builder.
 *
 * @internal
 */
trait LayoutEntityHelperTrait {

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
    return $entity instanceof LayoutEntityDisplayInterface || $this->isEntityUsingFieldOverride($entity);
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
  protected function getInlineBlockRevisionIdsInSections(array $sections) {
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
   * Gets the sections for an entity if any.
   *
   * @todo Replace this method with calls to the SectionStorageManagerInterface
   * method for getting sections from an entity in
   * https://www.drupal.org/node/2986403.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\layout_builder\Section[]|null
   *   The entity layout sections if available.
   */
  protected function getEntitySections(EntityInterface $entity) {
    /** @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_manager */
    $section_manager = \Drupal::service('plugin.manager.layout_builder.section_storage');
    $view_mode = 'default';
    $contexts['entity'] = EntityContext::fromEntity($entity);
    if ($entity instanceof FieldableEntityInterface) {
      $display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);
      $contexts['display'] = EntityContext::fromEntity($display);
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    }

    $section_storage = $section_manager->findByContext($contexts, new CacheableMetadata());
    return $section_storage ? $section_storage->getSections() : [];
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
    $inline_block_components = [];
    foreach ($sections as $section) {
      foreach ($section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof DerivativeInspectionInterface && $plugin->getBaseId() === 'inline_block') {
          $inline_block_components[] = $component;
        }
      }
    }
    return $inline_block_components;
  }

  /**
   * Determines if an entity is using a field for the layout override.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity is using a field for a layout override.
   */
  protected function isEntityUsingFieldOverride(EntityInterface $entity) {
    return $entity instanceof FieldableEntityInterface && $entity->hasField(OverridesSectionStorage::FIELD_NAME);
  }

}

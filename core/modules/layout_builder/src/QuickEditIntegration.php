<?php

namespace Drupal\layout_builder;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Helper methods for QuickEdit module integration.
 *
 * @internal
 */
class QuickEditIntegration {

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new QuickEditIntegration object.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, ContextRepositoryInterface $context_repository, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->contextRepository = $context_repository;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Alters the entity view build for Quick Edit compatibility.
   *
   * When rendering fields outside of normal view modes, Quick Edit requires
   * that modules identify themselves with a view mode in the format module
   * name-id.
   *
   * @param array $build
   *   The built entity render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The entity view display.
   *
   * @see hook_quickedit_render_field()
   * @see layout_builder_quickedit_render_field()
   *
   * @internal
   */
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if (!$entity instanceof FieldableEntityInterface || !isset($build['_layout_builder']) || !$this->currentUser->hasPermission('access in-place editing')) {
      return;
    }
    // should I be altering all fields here? because it will not get new field unless all are invalide?
    $non_empty_sections = [];
    foreach (Element::children($build['_layout_builder']) as $delta) {
      $section = &$build['_layout_builder'][$delta];
      // Skip blank sections.
      if (empty($section)) {
        continue;
      }
      $non_empty_sections[$delta] = &$section;
    }
    if ($non_empty_sections) {
      $sections_hash = hash('sha256', serialize($non_empty_sections));

      foreach ($non_empty_sections as $delta => &$section) {
        /** @var \Drupal\Core\Layout\LayoutDefinition $layout */
        $layout = $section['#layout'];
        $regions = $layout->getRegionNames();

        foreach ($regions as $region) {
          if (isset($section[$region])) {
            foreach ($section[$region] as $component_uuid => &$component) {
              if ($this->supportQuickEditOnComponent($component, $entity)) {
                $component['content']['#view_mode'] = implode('-', [
                  'layout_builder',
                  $display->getMode(),
                  'component',
                  $delta,
                  // Replace the dashes in the component uuid so because we need
                  // use dashes to join the parts.
                  str_replace('-', '_', $component_uuid),
                  $entity->id(),
                  $sections_hash,
                ]);
              }
            }
          }
        }
      }
      // Alter the view_mode of all fields outside of the Layout Builder
      // sections to force QuickEdit to request to field metadata.
      // @todo IN THIS ISSUE determine if this a bug in QuickEdit or just how
      //   client metadata needs to be cleared.
      //   @see https://www.drupal.org/project/drupal/issues/2966136
      foreach (Element::children($build) as $field_name) {
        if ($field_name !== '_layout_builder') {
          $field_build = &$build[$field_name];
          if (isset($field_build['#view_mode'])) {
            $field_build['#view_mode'] = "layout_builder-{$display->getMode()}-non_component-$sections_hash";
          }
        }
      }
    }
  }

  /**
   * Re-renders a field rendered by Layout Builder, edited with Quick Edit.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param string $view_mode_id
   *   The view mode id.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   The re-rendered field.
   *
   * @internal
   */
  public function quickEditRenderField(EntityInterface $entity, $field_name, $view_mode_id, $langcode) {
    $build = [];

    if ($entity instanceof FieldableEntityInterface) {
      list(, $entity_view_mode, $field_type, $info) = explode('-', $view_mode_id, 4);
      $entity_build = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $entity_view_mode);
      if (isset($entity_build['#pre_render'])) {
        foreach ($entity_build['#pre_render'] as $callable) {
          $entity_build = call_user_func($callable, $entity_build);
        }
      }


      // Replace the underscores with dash to get back the component UUID.
      // @see \Drupal\layout_builder\QuickEditIntegration::entityViewAlter
      if ($field_type === 'component') {
        list($delta, $component_uuid) = explode('-', $info);
        $component_uuid = str_replace('_', '-', $component_uuid);
        if (isset($entity_build['_layout_builder'][$delta])) {
          foreach (Element::children($entity_build['_layout_builder'][$delta]) as $region) {
            if (isset($entity_build['_layout_builder'][$delta][$region][$component_uuid])) {
              return $entity_build['_layout_builder'][$delta][$region][$component_uuid];
            }
          }
        }
      }
      else {
        return $entity_build[$field_name];
      }

    }
    return $build;
  }

  /**
   * Determines whether a component has QuickEdit support.
   *
   * Only field_block components for view configurable fields should be
   * supported.
   *
   * @param array $component
   *   The component render array.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being displayed.
   *
   * @return bool
   *   Whether QuickEdit is supported on the component.
   *
   * @see \Drupal\layout_builder\Plugin\Block\FieldBlock
   */
  private function supportQuickEditOnComponent($component, FieldableEntityInterface $entity) {
    if (isset($component['content']['#field_name']) && isset($component['#base_plugin_id']) && $component['#base_plugin_id'] === 'field_block') {
      if ($entity->getFieldDefinition($component['content']['#field_name'])->isDisplayConfigurable('view')) {
        return TRUE;
      }
    }
    return FALSE;
  }

}

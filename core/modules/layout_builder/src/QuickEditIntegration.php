<?php

namespace Drupal\layout_builder;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper methods for QuickEdit module integration.
 *
 * @internal
 */
class QuickEditIntegration implements ContainerInjectionInterface {

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
   * Constructs a new QuickEditIntegration object.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, ContextRepositoryInterface $context_repository, AccountInterface $current_user) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->contextRepository = $context_repository;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('context.repository'),
      $container->get('current_user')
    );
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
    $non_empty_sections = [];
    foreach (Element::children($build['_layout_builder']) as $delta) {
      $section = &$build['_layout_builder'][$delta];
      // Skip blank sections.
      if (empty($section)) {
        continue;
      }
      $non_empty_sections[] = $section;

      /** @var \Drupal\Core\Layout\LayoutDefinition $layout */
      $layout = $section['#layout'];
      $regions = $layout->getRegionNames();

      foreach ($regions as $region) {
        if (isset($section[$region])) {
          foreach ($section[$region] as $component_uuid => &$component) {
            if ($this->supportQuickEditOnComponent($component, $entity)) {
              $component['content']['#view_mode'] = implode('-', [
                'layout_builder',
                $delta,
                // Replace the dashes in the component uuid so because we need
                // use dashes to join the parts.
                str_replace('-', '_', $component_uuid),
                $entity->id(),
              ]);
            }
          }
        }
      }
    }
    if ($non_empty_sections) {
      $sections_hash = hash('sha256', serialize($non_empty_sections));
    }
    else {
      // Set the section hash to indicate we are not using the Layout Builder.
      // If the Layout Builder was previously enabled for this entity the
      // QuickEdit metadata will need to be cleared on the client.
      $sections_hash = 'no_sections';

    }
    $build['#attached']['drupalSettings']['layout_builder']['section_hashes'][$entity->getEntityTypeId() . ':' . $entity->id() . ':' . $display->getMode()] = [
      'hash' => $sections_hash,
      'quickedit_storage_prefix' => $entity->getEntityTypeId() . '/' . $entity->id(),
    ];
    $build['#attached']['library'][] = 'layout_builder/drupal.layout_builder_quickedit';
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
    list(, $delta, $component_uuid, $entity_id) = explode('-', $view_mode_id);
    // Replace the underscores with dash to get back the component UUID.
    // @see \Drupal\layout_builder\QuickEditIntegration::entityViewAlter
    $component_uuid = str_replace('_', '-', $component_uuid);
    if ($entity instanceof FieldableEntityInterface) {
      $view_display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode_id);
      $cacheable_metadata = new CacheableMetadata();
      $section_list = $this->sectionStorageManager->findByContext(
        [
          'display' => EntityContext::fromEntity($view_display),
          'entity' => EntityContext::fromEntity($entity),
          'view_mode' => new Context(new ContextDefinition('string'), $view_mode_id),
        ],
        $cacheable_metadata
      );

      $component = $section_list->getSection($delta)
        ->getComponent($component_uuid);
      $contexts = $this->contextRepository->getAvailableContexts();
      $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity);
      $block = $component->toRenderArray($contexts);
      $build = $block['content'];
      $build['#view_mode'] = $view_mode_id;
      $cacheable_metadata->applyTo($build);
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

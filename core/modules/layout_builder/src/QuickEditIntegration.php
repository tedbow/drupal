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
use Drupal\Core\Render\Element;
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
   * Constructs a new QuickEditIntegration object.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, ContextRepositoryInterface $context_repository) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('context.repository')
    );
  }

  /**
   * Alters the entity for Quick Edit compatibility.
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
    if (!isset($build['_layout_builder'])) {
      return;
    }
    foreach (Element::children($build['_layout_builder']) as $delta) {
      $section = &$build['_layout_builder'][$delta];
      /** @var \Drupal\Core\Layout\LayoutDefinition $layout */
      $layout = $section['#layout'];
      $regions = $layout->getRegionNames();

      foreach ($regions as $region) {
        if (isset($section[$region])) {
          foreach ($section[$region] as $component_uuid => &$component) {
            if (isset($component['content']) && isset($component['#base_plugin_id']) && $component['#base_plugin_id'] === 'field_block') {
              $component['content']['#view_mode'] = implode('-', [
                'layout_builder',
                $delta,
                $component_uuid,
              ]);
            }
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
    list(, $delta, $component_uuid) = explode('-', $view_mode_id, 3);
    if ($entity instanceof FieldableEntityInterface) {
      $view_display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode_id);
      $cacheableMetaData = new CacheableMetadata();
      $section_list = $this->sectionStorageManager->findByContext(
        [
          'display' => EntityContext::fromEntity($view_display),
          'entity' => EntityContext::fromEntity($entity),
          'view_mode' => new Context(new ContextDefinition('string'), $view_mode_id),
        ],
        $cacheableMetaData
      );

      $component = $section_list->getSection($delta)
        ->getComponent($component_uuid);
      $contexts = $this->contextRepository->getAvailableContexts();
      // @todo Change to use EntityContextDefinition in
      // https://www.drupal.org/project/drupal/issues/2932462.
      $contexts['layout_builder.entity'] = new Context(new ContextDefinition("entity:{$entity->getEntityTypeId()}", new TranslatableMarkup('@entity being viewed', [
        '@entity' => $entity->getEntityType()
          ->getLabel(),
      ])), $entity);
      $block = $component->toRenderArray($contexts);
      $build = $block['content'];
      $build['#view_mode'] = $view_mode_id;
      $cacheableMetaData->applyTo($build);
    }
    return $build;
  }

}

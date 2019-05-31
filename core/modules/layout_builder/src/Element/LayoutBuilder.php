<?php

namespace Drupal\layout_builder\Element;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Url;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TranslatableSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a render element for building the Layout Builder UI.
 *
 * @RenderElement("layout_builder")
 *
 * @internal
 *   Plugin classes are internal.
 */
class LayoutBuilder extends RenderElement implements ContainerFactoryPluginInterface {

  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use LayoutBuilderHighlightTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LayoutBuilder.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   (optional) The entity type manager.
   *
   * @todo The current constructor signature is deprecated:
   *   - The $entity_type_manager parameter is optional but should become
   *   required. Deprecate in https://www.drupal.org/node/3058490.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->messenger = $messenger;
    if ($entity_type_manager === NULL) {
      @trigger_error('The entity_type.manager service must be passed to \Drupal\layout_builder\Element\LayoutBuilder::__construct(). It was added in Drupal 8.8.0 and will be required before Drupal 9.0.0.', E_USER_DEPRECATED);
      $entity_type_manager = \Drupal::service('entity_type.manager');
    }
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('layout_builder.tempstore_repository'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#section_storage' => NULL,
      '#pre_render' => [
        [$this, 'preRender'],
      ],
    ];
  }

  /**
   * Pre-render callback: Renders the Layout Builder UI.
   */
  public function preRender($element) {
    if ($element['#section_storage'] instanceof SectionStorageInterface) {
      $element['layout_builder'] = $this->layout($element['#section_storage']);
    }
    return $element;
  }

  /**
   * Renders the Layout UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   A render array.
   */
  protected function layout(SectionStorageInterface $section_storage) {
    $this->prepareLayout($section_storage);
    $is_translation = $this->isTranslation($section_storage);

    $output = [];
    if ($this->isAjax()) {
      $output['status_messages'] = [
        '#type' => 'status_messages',
      ];
    }
    $count = 0;
    for ($i = 0; $i < $section_storage->count(); $i++) {
      if (!$is_translation) {
        $output[] = $this->buildAddSectionLink($section_storage, $count);
      }

      $output[] = $this->buildAdministrativeSection($section_storage, $count);
      $count++;
    }
    if (!$is_translation) {
      $output[] = $this->buildAddSectionLink($section_storage, $count);
    }

    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    // As the Layout Builder UI is typically displayed using the frontend theme,
    // it is not marked as an administrative page at the route level even though
    // it performs an administrative task. Mark this as an administrative page
    // for JavaScript.
    $output['#attached']['drupalSettings']['path']['currentPathIsAdmin'] = TRUE;
    $output['#type'] = 'container';
    $output['#attributes']['id'] = 'layout-builder';
    $output['#attributes']['class'][] = 'layout-builder';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;

    if ($is_translation) {
      $has_translatable_component = FALSE;
      foreach ($section_storage->getSections() as $section) {
        foreach ($section->getComponents() as $uuid => $component) {
          if ($component->hasTranslatableConfiguration()) {
            $has_translatable_component = TRUE;
            break 2;
          }
        }
      }
      if (!$has_translatable_component) {
        $this->messenger()->addStatus($this->t('There are currently no settings that can be translated'));
      }
    }

    return $output;
  }

  /**
   * Prepares a layout for use in the UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   */
  protected function prepareLayout(SectionStorageInterface $section_storage) {
    // If the layout has pending changes, add a warning.
    if ($this->layoutTempstoreRepository->has($section_storage)) {
      $this->messenger->addWarning($this->t('You have unsaved changes.'));
    }
    // If the layout is an override that has not yet been overridden, copy the
    // sections from the corresponding default.
    elseif ($section_storage instanceof OverridesSectionStorageInterface && !$section_storage->isOverridden()) {
      $sections = $section_storage->getDefaultSectionStorage()->getSections();
      foreach ($sections as $section) {
        $section_storage->appendSection($section);
      }
      $this->layoutTempstoreRepository->set($section_storage);
    }
  }

  /**
   * Builds a link to add a new section at a given delta.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   A render array for a link.
   */
  protected function buildAddSectionLink(SectionStorageInterface $section_storage, $delta) {
    $storage_type = $section_storage->getStorageType();
    $storage_id = $section_storage->getStorageId();

    // If the delta and the count are the same, it is either the end of the
    // layout or an empty layout.
    if ($delta === count($section_storage)) {
      if ($delta === 0) {
        $title = $this->t('Add section');
      }
      else {
        $title = $this->t('Add section <span class="visually-hidden">at end of layout</span>');
      }
    }
    // If the delta and the count are different, it is either the beginning of
    // the layout or in between two sections.
    else {
      if ($delta === 0) {
        $title = $this->t('Add section <span class="visually-hidden">at start of layout</span>');
      }
      else {
        $title = $this->t('Add section <span class="visually-hidden">between @first and @second</span>', ['@first' => $delta, '@second' => $delta + 1]);
      }
    }

    return [
      'link' => [
        '#type' => 'link',
        '#title' => $title,
        '#url' => Url::fromRoute('layout_builder.choose_section',
          [
            'section_storage_type' => $storage_type,
            'section_storage' => $storage_id,
            'delta' => $delta,
          ],
          [
            'attributes' => [
              'class' => [
                'use-ajax',
                'layout-builder__link',
                'layout-builder__link--add',
              ],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ],
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-builder__add-section'],
        'data-layout-builder-highlight-id' => $this->sectionAddHighlightId($delta),
      ],
    ];
  }

  /**
   * Builds the render array for the layout section while editing.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   *
   * @return array
   *   The render array for a given section.
   */
  protected function buildAdministrativeSection(SectionStorageInterface $section_storage, $delta) {
    $storage_type = $section_storage->getStorageType();
    $storage_id = $section_storage->getStorageId();
    $section = $section_storage->getSection($delta);
    $sections_editable = !$this->isTranslation($section_storage);
    $layout = $section->getLayout();
    $build = $section->toRenderArray($this->getAvailableContexts($section_storage), TRUE);
    $layout_definition = $layout->getPluginDefinition();

    $region_labels = $layout_definition->getRegionLabels();
    foreach ($layout_definition->getRegions() as $region => $info) {
      if (!empty($build[$region])) {
        foreach (Element::children($build[$region]) as $uuid) {
          if ($sections_editable) {
            $build[$region][$uuid]['#attributes']['class'][] = 'js-layout-builder-block';
          }
          $build[$region][$uuid]['#attributes']['class'][] = 'layout-builder-block';
          $build[$region][$uuid]['#attributes']['data-layout-block-uuid'] = $uuid;
          $build[$region][$uuid]['#attributes']['data-layout-builder-highlight-id'] = $this->blockUpdateHighlightId($uuid);
          if ($contextual_link_element = $this->getContextualLinkElement($section_storage, $delta, $region, $uuid)) {
            $build[$region][$uuid]['#contextual_links'] = $contextual_link_element;
          }
        }
      }

      $build[$region]['layout_builder_add_block']['link'] = [
        '#type' => 'link',
        '#access' => $sections_editable,
        // Add one to the current delta since it is zero-indexed.
        '#title' => $this->t('Add block <span class="visually-hidden">in section @section, @region region</span>', ['@section' => $delta + 1, '@region' => $region_labels[$region]]),
        '#url' => Url::fromRoute('layout_builder.choose_block',
          [
            'section_storage_type' => $storage_type,
            'section_storage' => $storage_id,
            'delta' => $delta,
            'region' => $region,
          ],
          [
            'attributes' => [
              'class' => [
                'use-ajax',
                'layout-builder__link',
                'layout-builder__link--add',
              ],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ];
      $build[$region]['layout_builder_add_block']['#type'] = 'container';
      $build[$region]['layout_builder_add_block']['#attributes'] = [
        'class' => ['layout-builder__add-block'],
        'data-layout-builder-highlight-id' => $this->blockAddHighlightId($delta, $region),
      ];
      $build[$region]['layout_builder_add_block']['#weight'] = 1000;
      $build[$region]['#attributes']['data-region'] = $region;
      $build[$region]['#attributes']['class'][] = 'layout-builder__region';
      $build[$region]['#attributes']['class'][] = 'js-layout-builder-region';
      $build[$region]['#attributes']['role'] = 'group';
      $build[$region]['#attributes']['aria-label'] = $this->t('@region region in section @section', [
        '@region' => $info['label'],
        '@section' => $delta + 1,
      ]);

      // Get weights of all children for use by the region label.
      $weights = array_map(function ($a) {
        return isset($a['#weight']) ? $a['#weight'] : 0;
      }, $build[$region]);

      // The region label is made visible when the move block dialog is open.
      $build[$region]['region_label'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout__region-info', 'layout-builder__region-label'],
          // A more detailed version of this information is already read by
          // screen readers, so this label can be hidden from them.
          'aria-hidden' => TRUE,
        ],
        '#markup' => $this->t('Region: @region', ['@region' => $info['label']]),
        // Ensures the region label is displayed first.
        '#weight' => min($weights) - 1,
      ];
    }

    $build['#attributes']['data-layout-update-url'] = Url::fromRoute('layout_builder.move_block', [
      'section_storage_type' => $storage_type,
      'section_storage' => $storage_id,
    ])->toString();

    $build['#attributes']['data-layout-delta'] = $delta;
    $build['#attributes']['class'][] = 'layout-builder__layout';
    $build['#attributes']['data-layout-builder-highlight-id'] = $this->sectionUpdateHighlightId($delta);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-builder__section'],
        'role' => 'group',
        'aria-label' => $this->t('Section @section', ['@section' => $delta + 1]),
      ],
      'remove' => [
        '#type' => 'link',
        '#access' => $sections_editable,
        '#title' => $this->t('Remove section <span class="visually-hidden">@section</span>', ['@section' => $delta + 1]),
        '#url' => Url::fromRoute('layout_builder.remove_section', [
          'section_storage_type' => $storage_type,
          'section_storage' => $storage_id,
          'delta' => $delta,
        ]),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'layout-builder__link',
            'layout-builder__link--remove',
          ],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ],
      // The section label is added to sections without a "Configure section"
      // link, and is only visible when the move block dialog is open.
      'section_label' => [
        '#markup' => $this->t('<span class="layout-builder__section-label" aria-hidden="true">Section @section</span>', ['@section' => $delta + 1]),
        '#access' => !$layout instanceof PluginFormInterface,
      ],
      'configure' => [
        '#type' => 'link',
        // There are two instances of @section, the one wrapped in
        // .visually-hidden is for screen readers. The one wrapped in
        // .layout-builder__section-label is only visible when the
        // move block dialog is open and it is not seen by screen readers.
        '#title' => $this->t('Configure section <span class="visually-hidden">@section</span><span aria-hidden="true" class="layout-builder__section-label">@section</span>', ['@section' => $delta + 1]),
        '#access' => $layout instanceof PluginFormInterface && $sections_editable,
        '#url' => Url::fromRoute('layout_builder.configure_section', [
          'section_storage_type' => $storage_type,
          'section_storage' => $storage_id,
          'delta' => $delta,
        ]),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'layout-builder__link',
            'layout-builder__link--configure',
          ],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ],
      'layout-builder__section' => $build,
    ];
  }

  /**
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param $delta
   *   The section delta.
   * @param $region
   *   The region.
   * @param $uuid
   *   The UUID of the component.
   * @param $is_translation
   *   Whether the
   * @param \Drupal\layout_builder\Section $section
   *
   * @return array
   */
  protected function getContextualLinkElement(SectionStorageInterface $section_storage, $delta, $region, $uuid) {
    $section = $section_storage->getSection($delta);
    $contextual_link_settings = [
      'route_parameters' => [
        'section_storage_type' => $section_storage->getStorageType(),
        'section_storage' => $section_storage->getStorageId(),
        'delta' => $delta,
        'region' => $region,
        'uuid' => $uuid,
      ],
    ];
    if ($this->isTranslation($section_storage)) {
      $component = $section->getComponent($uuid);
      if ($component->hasTranslatableConfiguration()) {
        $contextual_group = 'layout_builder_block_translation';
        /** @var \Drupal\Core\Language\LanguageInterface $language */
        if ($language = $section_storage->getTranslationLanguage()) {
          $contextual_link_settings['route_parameters']['langcode'] = $language->getId();
        }

        /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
        $plugin = $component->getPlugin();
        if ($plugin instanceof DerivativeInspectionInterface && $plugin->getBaseId() === 'inline_block') {
          $configuration = $plugin->getConfiguration();
          /** @var \Drupal\block_content\Entity\BlockContent $block */
          $block = $this->entityTypeManager->getStorage('block_content')
            ->loadRevision($configuration['block_revision_id']);
          if ($block->isTranslatable()) {
            $contextual_group = 'layout_builder_inline_block_translation';
          }
        }
      }
    }
    else {
      // Add metadata about the current operations available in
      // contextual links. This will invalidate the client-side cache of
      // links that were cached before the 'move' link was added.
      // @see layout_builder.links.contextual.yml
      $contextual_link_settings['metadata'] = [
        'operations' => 'move:update:remove',
      ];
      $contextual_group = 'layout_builder_block';
    }
    if (isset($contextual_group)) {
      $contextual_link_element = [
        $contextual_group => $contextual_link_settings,
      ];

    }
    return $contextual_link_element;
  }

  /**
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *
   * @return bool
   */
  protected function isTranslation(SectionStorageInterface $section_storage) {
    return $section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation();
  }

}

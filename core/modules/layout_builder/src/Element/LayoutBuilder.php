<?php

namespace Drupal\layout_builder\Element;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Url;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutBuilderTranslatablePluginInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TranslatableSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a render element for building the Layout Builder UI.
 *
 * @RenderElement("layout_builder")
 */
class LayoutBuilder extends RenderElement implements ContainerFactoryPluginInterface {

  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->messenger = $messenger;
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
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#section_storage' => NULL,
      '#language' => NULL,
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
      $language = $element['#lanuauge'] ?: NULL;
      $element['layout_builder'] = $this->layout($element['#section_storage'], $language);

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
    $sections_editable = !($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation());

    $output = [];
    if ($this->isAjax()) {
      $output['status_messages'] = [
        '#type' => 'status_messages',
      ];
    }
    $count = 0;
    for ($i = 0; $i < $section_storage->count(); $i++) {
      if ($sections_editable) {
        $output[] = $this->buildAddSectionLink($section_storage, $count);
      }

      $output[] = $this->buildAdministrativeSection($section_storage, $count);
      $count++;
    }
    if ($sections_editable) {
      $output[] = $this->buildAddSectionLink($section_storage, $count);
    }

    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#type'] = 'container';
    $output['#attributes']['id'] = 'layout-builder';
    $output['#attributes']['class'][] = 'layout-builder';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;

    // @todo Add message if not components have translate links!
    //    "There are no settings to translate"

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
      if ($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation()) {
        // @todo Copy in any change from the default translation and then
        //   reapply any translated labels where the original labels has not
        //   changed. This should avoid data loss if the layout has been
        //   updated since this layout override has started. This probably also
        //   needs to be done on save to avoid overriding the layout if it was
        //   saved since the last time this page was opened.
      }
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
        $title = $this->t('Add Section');
      }
      else {
        $title = $this->t('Add Section <span class="visually-hidden">at end of layout</span>');
      }
    }
    // If the delta and the count are different, it is either the beginning of
    // the layout or in between two sections.
    else {
      if ($delta === 0) {
        $title = $this->t('Add Section <span class="visually-hidden">at start of layout</span>');
      }
      else {
        $title = $this->t('Add Section <span class="visually-hidden">between @first and @second</span>', ['@first' => $delta, '@second' => $delta + 1]);
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
    $sections_editable = !($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation());
    $layout = $section->getLayout();
    $build = $section->toRenderArray($this->getAvailableContexts($section_storage), TRUE);
    $layout_definition = $layout->getPluginDefinition();

    $region_labels = $layout_definition->getRegionLabels();
    foreach ($layout_definition->getRegions() as $region => $info) {
      if (!empty($build[$region])) {
        foreach (Element::children($build[$region]) as $uuid) {
          if ($sections_editable) {
            $build[$region][$uuid]['#attributes']['class'][] = 'draggable';
          }

          $build[$region][$uuid]['#attributes']['data-layout-block-uuid'] = $uuid;
          $contextual_link_settings = [
            'route_parameters' => [
              'section_storage_type' => $storage_type,
              'section_storage' => $storage_id,
              'delta' => $delta,
              'region' => $region,
              'uuid' => $uuid,
            ],
          ];
          if ($sections_editable) {
            $build[$region][$uuid]['#contextual_links'] = [
              'layout_builder_block' => $contextual_link_settings,
            ];
          }
          elseif ($this->componentHasTranslatableConfiguration($section_storage, $section->getComponent($uuid))) {
            $build[$region][$uuid]['#contextual_links'] = [
              'layout_builder_block_translation' => $contextual_link_settings,
            ];
          }
        }
      }

      $build[$region]['layout_builder_add_block']['link'] = [
        '#type' => 'link',
        '#access' => $sections_editable,
        // Add one to the current delta since it is zero-indexed.
        '#title' => $this->t('Add Block <span class="visually-hidden">in section @section, @region region</span>', ['@section' => $delta + 1, '@region' => $region_labels[$region]]),
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
      $build[$region]['layout_builder_add_block']['#attributes'] = ['class' => ['layout-builder__add-block']];
      $build[$region]['layout_builder_add_block']['#weight'] = 1000;
      $build[$region]['#attributes']['data-region'] = $region;
      $build[$region]['#attributes']['class'][] = 'layout-builder__region';
    }

    $build['#attributes']['data-layout-update-url'] = Url::fromRoute('layout_builder.move_block', [
      'section_storage_type' => $storage_type,
      'section_storage' => $storage_id,
    ])->toString();
    $build['#attributes']['data-layout-delta'] = $delta;
    $build['#attributes']['class'][] = 'layout-builder__layout';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-builder__section'],
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
      'configure' => [
        '#type' => 'link',
        '#title' => $this->t('Configure section <span class="visually-hidden">@section</span>', ['@section' => $delta + 1]),
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
   * Determines if the component is translatable.
   *
   * @todo determine how handle other settings that need to be translated
   *    such as the inline blocks use case.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The component to check.
   *
   * @return bool
   *   TRUE if the default component has translatable settings, otherwise FALSE.
   */
  protected function componentHasTranslatableConfiguration(SectionStorageInterface $section_storage, SectionComponent $component) {
    if ($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation()) {
      $plugin = $component->getPlugin();
      $contexts = $section_storage->getContexts();
      if ($plugin instanceof LayoutBuilderTranslatablePluginInterface) {
        return $plugin->hasTranslatableConfiguration();
      }
      elseif ($plugin instanceof ConfigurableInterface) {
        $configuration = $plugin->getConfiguration();
        return !empty($configuration['label_display']) && !empty($configuration['label']);
      }
    }
    return FALSE;
  }

}

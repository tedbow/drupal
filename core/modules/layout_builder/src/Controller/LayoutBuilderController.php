<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines a controller to provide the Layout Builder admin UI.
 *
 * @internal
 */
class LayoutBuilderController implements ContainerInjectionInterface {

  use LayoutBuilderContextTrait;
  use StringTranslationTrait;
  use AjaxHelperTrait;

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
   * LayoutBuilderController constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, MessengerInterface $messenger) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('messenger')
    );
  }

  /**
   * Provides a title callback.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return string
   *   The title for the layout page.
   */
  public function title(SectionStorageInterface $section_storage) {
    return $this->t('Edit layout for %label', ['%label' => $section_storage->label()]);
  }

  /**
   * Renders the Layout UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param bool $is_rebuilding
   *   (optional) Indicates if the layout is rebuilding, defaults to FALSE.
   *
   * @return array
   *   A render array.
   */
  public function layout(SectionStorageInterface $section_storage, $is_rebuilding = FALSE) {
    $this->prepareLayout($section_storage, $is_rebuilding);

    $output = [];
    if ($this->isAjax()) {
      $output['status_messages'] = [
        '#type' => 'status_messages',
      ];
    }
    $count = 0;
    for ($i = 0; $i < $section_storage->count(); $i++) {
      $output[] = $this->buildAddSectionLink($section_storage, $count);
      $output[] = $this->buildAdministrativeSection($section_storage, $count);
      $count++;
    }
    $output[] = $this->buildAddSectionLink($section_storage, $count);
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#type'] = 'container';
    $output['#attributes']['id'] = 'layout-builder';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Prepares a layout for use in the UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param bool $is_rebuilding
   *   Indicates if the layout is rebuilding.
   */
  protected function prepareLayout(SectionStorageInterface $section_storage, $is_rebuilding) {
    // If the layout has pending changes, add a warning.
    if ($this->layoutTempstoreRepository->has($section_storage)) {
      $this->messenger->addWarning($this->t('You have unsaved changes.'));
    }

    // Only add sections if the layout is new and empty.
    if (!$is_rebuilding && $section_storage->count() === 0) {
      $sections = [];
      // If this is an empty override, copy the sections from the corresponding
      // default.
      if ($section_storage instanceof OverridesSectionStorageInterface) {
        $sections = $section_storage->getDefaultSectionStorage()->getSections();
      }

      // For an empty layout, begin with a single section of one column.
      if (!$sections) {
        $sections[] = new Section('layout_onecol');
      }

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
    return [
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Add Section'),
        '#url' => Url::fromRoute('layout_builder.choose_section',
          [
            'section_storage_type' => $storage_type,
            'section_storage' => $storage_id,
            'delta' => $delta,
          ],
          [
            'attributes' => [
              'class' => ['use-ajax', 'new-section__link'],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ],
      '#type' => 'container',
      '#attributes' => [
        'class' => ['new-section'],
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

    $layout = $section->getLayout();
    $build = $section->toRenderArray($this->getAvailableContexts($section_storage), TRUE);
    $layout_definition = $layout->getPluginDefinition();

    foreach ($layout_definition->getRegions() as $region => $info) {
      if (!empty($build[$region])) {
        foreach ($build[$region] as $uuid => $block) {
          // Shift block output down a level in the render array to allow
          // navigation links.
          $build[$region][$uuid] = [
            '#type' => 'container',
            '#weight' => $build[$region][$uuid]['#weight'],
            'block_output' => $build[$region][$uuid],
            'layout_builder_reorder' => $this->createLayoutBuilderReorderNavigation($section_storage, $delta, $region, $uuid),
          ];
          $build[$region][$uuid]['#attributes']['class'][] = 'draggable';
          $build[$region][$uuid]['#attributes']['data-layout-block-uuid'] = $uuid;
          $build[$region][$uuid]['block_output']['#contextual_links'] = [
            'layout_builder_block' => [
              'route_parameters' => [
                'section_storage_type' => $storage_type,
                'section_storage' => $storage_id,
                'delta' => $delta,
                'region' => $region,
                'uuid' => $uuid,
              ],
            ],
          ];
        }
      }

      $build[$region]['layout_builder_add_block']['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Block'),
        '#url' => Url::fromRoute('layout_builder.choose_block',
          [
            'section_storage_type' => $storage_type,
            'section_storage' => $storage_id,
            'delta' => $delta,
            'region' => $region,
          ],
          [
            'attributes' => [
              'class' => ['use-ajax', 'new-block__link'],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ];
      $build[$region]['layout_builder_add_block']['#type'] = 'container';
      $build[$region]['layout_builder_add_block']['#attributes'] = ['class' => ['new-block']];
      $build[$region]['layout_builder_add_block']['#weight'] = 1000;
      $build[$region]['#attributes']['data-region'] = $region;
      $build[$region]['#attributes']['class'][] = 'layout-builder--layout__region';
    }

    $build['#attributes']['data-layout-update-url'] = Url::fromRoute('layout_builder.move_block', [
      'section_storage_type' => $storage_type,
      'section_storage' => $storage_id,
    ])->toString();
    $build['#attributes']['data-layout-delta'] = $delta;
    $build['#attributes']['class'][] = 'layout-builder--layout';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-section'],
      ],
      'configure' => [
        '#type' => 'link',
        '#title' => $this->t('Configure section'),
        '#access' => $layout instanceof PluginFormInterface,
        '#url' => Url::fromRoute('layout_builder.configure_section', [
          'section_storage_type' => $storage_type,
          'section_storage' => $storage_id,
          'delta' => $delta,
        ]),
        '#attributes' => [
          'class' => ['use-ajax', 'configure-section'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ],
      'remove' => [
        '#type' => 'link',
        '#title' => $this->t('Remove section'),
        '#url' => Url::fromRoute('layout_builder.remove_section', [
          'section_storage_type' => $storage_type,
          'section_storage' => $storage_id,
          'delta' => $delta,
        ]),
        '#attributes' => [
          'class' => ['use-ajax', 'remove-section'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ],
      'layout-section' => $build,
    ];
  }

  /**
   * Saves the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function saveLayout(SectionStorageInterface $section_storage) {
    $section_storage->save();
    $this->layoutTempstoreRepository->delete($section_storage);

    if ($section_storage instanceof OverridesSectionStorageInterface) {
      $this->messenger->addMessage($this->t('The layout override has been saved.'));
    }
    else {
      $this->messenger->addMessage($this->t('The layout has been saved.'));
    }

    return new RedirectResponse($section_storage->getRedirectUrl()->setAbsolute()->toString());
  }

  /**
   * Cancels the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function cancelLayout(SectionStorageInterface $section_storage) {
    $this->layoutTempstoreRepository->delete($section_storage);

    $this->messenger->addMessage($this->t('The changes to the layout have been discarded.'));

    return new RedirectResponse($section_storage->getRedirectUrl()->setAbsolute()->toString());
  }

  /**
   * Creates Layout Builder navigation links.
   *
   * @return array
   *   Navigation links render array.
   */
  protected function createLayoutBuilderReorderNavigation(SectionStorageInterface $section_storage, $delta_from, $region, $uuid) {
    $previous_delta_to = NULL;
    $region_block_uuids = $this->getRegionComponentUuids($section_storage, $delta_from, $region);
    $current_block_index = array_search($uuid, $region_block_uuids, TRUE);
    if ($current_block_index > 0) {
      $previous_delta_to = $delta_from;
      $previous_region_to = $region;
      if ($current_block_index === 1) {
        $previous_preceding_block_uuid = NULL;
      }
      else {
        $previous_preceding_block_uuid = $region_block_uuids[$current_block_index - 2];
      }
    }
    else {
      $previous_region_to = $this->getSiblingRegion($section_storage, $delta_from, $region, 'previous');
      if ($previous_region_to) {
        $previous_delta_to = $delta_from;
      }
      else {
        if ($delta_from > 0) {
          $previous_delta_to = $delta_from - 1;
          $regions = $this->getRegionKeys($section_storage, $previous_delta_to);
          $previous_region_to = array_pop($regions);
        }
      }
      if ($previous_delta_to !== NULL && $previous_region_to !== NULL) {
        $region_block_uuids = $this->getRegionComponentUuids($section_storage, $previous_delta_to, $previous_region_to);
        if ($region_block_uuids) {
          $previous_preceding_block_uuid = array_pop($region_block_uuids);
        }
      }
    }
    $next_region_to = NULL;
    $next_preceding_block_uuid = NULL;
    if ($current_block_index + 1 < count($region_block_uuids)) {
      $next_delta_to = $delta_from;
      $next_region_to = $region;
      $next_preceding_block_uuid = $region_block_uuids[$current_block_index + 1];
    }
    else {
      $next_region_to = $this->getSiblingRegion($section_storage, $delta_from, $region, 'next');
      if ($next_region_to) {
        $next_delta_to = $delta_from;
      }
      else {
        if ($delta_from + 1 < $section_storage->count()) {
          $next_delta_to = $delta_from + 1;
          $regions = $this->getRegionKeys($section_storage, $next_delta_to);
          $next_region_to = array_shift($regions);
        }
      }
    }

    $links = [
      '#type' => 'container',
      '#weight' => -1000,
      '#attributes' => [
        'class' => 'layout-builder-reorder',
        'data-layout-builder-reorder' => TRUE,
      ],
    ];
    if ($previous_region_to !== NULL) {
      $links['previous'] = $this->createReorderLink($section_storage, $delta_from, $uuid, $previous_delta_to, $previous_preceding_block_uuid, $previous_region_to, 'previous');
    }
    if ($next_region_to !== NULL) {
      $links['next'] = $this->createReorderLink($section_storage, $delta_from, $uuid, $next_delta_to, $next_preceding_block_uuid, $next_region_to, 'next');
    }
    return $links;
  }

  /**
   * Gets the sibling region for another region in a section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region.
   * @param string $sibling_direction
   *   Either 'previous' or 'next'.
   *
   * @return null|string
   *   The sibling region if there is one.
   */
  protected function getSiblingRegion(SectionStorageInterface $section_storage, $delta, $region, $sibling_direction) {
    $regions = $this->getRegionKeys($section_storage, $delta);
    $sibling_index = array_search($region, $regions) + ($sibling_direction === 'previous' ? -1 : 1);
    return isset($regions[$sibling_index]) ? $regions[$sibling_index] : NULL;
  }

  /**
   * Gets the UUIDs for a sections components.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region.
   *
   * @return string[]
   *   The region
   */
  protected function getRegionComponentUuids(SectionStorageInterface $section_storage, $delta, $region) {
    $sortable = [];
    foreach ($section_storage->getSection($delta)->getComponents() as $component) {
      if ($component->getRegion() === $region) {
        $sortable[$component->getWeight()] = $component->getUuid();
      }
    };
    ksort($sortable);
    return array_values($sortable);
  }

  /**
   * Gets region keys for a section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   *
   * @return string[]
   *   The region keys.
   */
  protected function getRegionKeys(SectionStorageInterface $section_storage, $delta) {
    $regions = array_keys($section_storage->getSection($delta)
      ->getLayout()
      ->getPluginDefinition()
      ->getRegions());
    return $regions;
  }

  /**
   * Creates an reorder link.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   * @param $delta_from
   * @param $uuid
   * @param $delta_to
   * @param $preceding_block_uuid
   * @param $region_to
   *
   * @return array
   *   The link render array.
   */
  protected function createReorderLink(SectionStorageInterface $section_storage, $delta_from, $uuid, $delta_to, $preceding_block_uuid, $region_to, $direction) {
    $route_arguments = [
      'section_storage' => $section_storage->getStorageId(),
      'delta_from' => $delta_from,
      'delta_to' => $delta_to,
      'direction_focus' => $direction,
      'block_uuid' => $uuid,
      'preceding_block_uuid' => $preceding_block_uuid,
      'region_to' => $region_to,
      'section_storage_type' => $section_storage->getStorageType(),
    ];
    $url = Url::fromRoute('layout_builder.move_block', $route_arguments);
    unset($route_arguments['section_storage'], $route_arguments['section_storage_type']);
    foreach ($route_arguments as $key => $route_argument) {
      $attributes["data-$key"] = $route_argument;
    }
    $attributes['class'] = ['layout-reorder-previous', 'use-ajax'];
    $link = [
      '#type' => 'link',
      '#title' => $direction === 'previous' ? $this->t('Previous') : $this->t('Next'),
      '#url' => $url,
      '#attributes' => $attributes,
    ];
    return $link;
  }

}

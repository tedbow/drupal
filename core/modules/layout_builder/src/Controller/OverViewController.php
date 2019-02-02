<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a overview of the current sections.
 */
class OverViewController {

  use StringTranslationTrait;
  use LayoutRebuildTrait;


  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * Create the overview.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   The overview render array.
   */
  public function overview(SectionStorageInterface $section_storage) {
    $this->sectionStorage = $section_storage;

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Block'),
        $this->t('Operations'),
      ],
      '#sticky' => TRUE,
      '#attributes' => [
        'id' => 'blocks',
        'class' => ['layout-overview'],
      ],
    ];
    for ($i = 0; $i < count($section_storage); $i++) {
      $table["add-section-$i"] = $this->buildAddSectionLink($section_storage, $i);
      $table += $this->buildAdministrativeSection($section_storage, $i);
    }
    $table["add-section-$i"] = $this->buildAddSectionLink($section_storage, $i);
    $output['table'] = $table;
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#attributes']['id'] = 'layout-builder';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;

    return $output;
  }

  /**
   * Gets the operations for a given component.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The component.
   *
   * @return array
   *   An associative array of operation link data for this list, keyed by
   *   operation name, containing the following key-value pairs:
   *   - title: The localized title of the operation.
   *   - url: An instance of \Drupal\Core\Url for the operation URL.
   *   - weight: The weight of this operation.
   */
  protected function getComponentOperations(SectionStorageInterface $section_storage, $delta, SectionComponent $component) {
    $route_parameters = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
      'delta' => $delta,
      'region' => $component->getRegion(),
      'uuid' => $component->getUuid(),
    ];
    $operations['configure'] = [
      'title' => $this->t('Configure'),
      'url' => Url::fromRoute('layout_builder.update_block', $route_parameters, $this->getOverviewOptions(TRUE)),
      'attributes' => [
        'class' => ['use-ajax', 'configure-block'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];
    // @todo Add "move" operation https://www.drupal.org/project/drupal/issues/2995689

    $operations['move'] = [
      'title' => $this->t('Move'),
      'url' => Url::fromRoute('layout_builder.move_block_form', $route_parameters, $this->getOverviewOptions(TRUE)),
      'attributes' => [
        'class' => ['use-ajax', 'configure-block'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];
    
    $operations['remove'] = [
      'title' => $this->t('Remove'),
      'url' => Url::fromRoute('layout_builder.remove_block', $route_parameters, $this->getOverviewOptions(TRUE)),
      'attributes' => [
        'class' => ['use-ajax', 'remove-block'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];
    return $operations;
  }

  /**
   * Gets the operations for a given section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   *
   * @return array
   *   An associative array of operation link data for this list, keyed by
   *   operation name, containing the following key-value pairs:
   *   - title: The localized title of the operation.
   *   - url: An instance of \Drupal\Core\Url for the operation URL.
   *   - weight: The weight of this operation.
   */
  protected function getSectionOperations(SectionStorageInterface $section_storage, $delta) {
    $route_parameters = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
      'delta' => $delta,
    ];
    if ($section_storage->getSection($delta)->getLayout() instanceof PluginFormInterface) {
      $operations['configure'] = [
        'title' => $this->t('Configure section'),
        'url' => Url::fromRoute('layout_builder.configure_section', $route_parameters, $this->getOverviewOptions(TRUE)),
        'attributes' => [
          'class' => ['use-ajax', 'configure-section'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ];
    }
    $operations['remove'] = [
      'title' => $this->t('Remove section'),
      'url' => Url::fromRoute('layout_builder.remove_section', $route_parameters, $this->getOverviewOptions(TRUE)),
      'attributes' => [
        'class' => ['use-ajax', 'remove-section'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];
    return $operations;
  }

  /**
   * Gets the operations for a given region.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section.
   * @param $region
   *   The region.
   *
   * @return array
   *   An associative array of operation link data for this list, keyed by
   *   operation name, containing the following key-value pairs:
   *   - title: The localized title of the operation.
   *   - url: An instance of \Drupal\Core\Url for the operation URL.
   *   - weight: The weight of this operation.
   */
  protected function getRegionOperations(SectionStorageInterface $section_storage, $delta, $region) {
    $operations['add_block'] = [
      'title' => $this->t('Add Block'),
      'url' => Url::fromRoute('layout_builder.choose_block',
        [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'delta' => $delta,
          'region' => $region,
        ],
        [
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
            'data-dialog-renderer' => 'off_canvas',
          ],
        ] + $this->getOverviewOptions(TRUE)
      ),
    ];
    return $operations;
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
      '#attributes' => [
        'class' => ['region-message'],
      ],
      'title' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['new-section'],
          'no_striping' => TRUE,
        ],
        '#wrapper_attributes' => [
          'colspan' => 3,
        ],
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
            ] + $this->getOverviewOptions(TRUE)
          ),
        ],
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
    $layout_definition = $section->getLayout()->getPluginDefinition();

    $build = [];
    $build["section-$delta"] = [
      '#attributes' => [
        'class' => ['region-title'],
        'no_striping' => TRUE,
      ],
      'label' => [
        '#markup' => $layout_definition->getLabel(),
      ],
      'operations' => [
        '#type' => 'operations',
        '#links' => $this->getSectionOperations($section_storage, $delta),
      ],
    ];

    $components = $section->getComponents();
    uasort($components, function (SectionComponent $a, SectionComponent $b) {
      return $a->getWeight() > $b->getWeight() ? 1 : -1;
    });
    foreach ($layout_definition->getRegions() as $region => $region_info) {
      $build["section-$delta-region-$region"] = [
        'title' => [
          '#markup' => $layout_definition->getRegionLabels()[$region],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $this->getRegionOperations($section_storage, $delta, $region),
        ],
      ];

      foreach ($components as $component) {
        if ($component->getRegion() !== $region) {
          continue;
        }
        $region = $component->getRegion();
        $uuid = $component->getUuid();
        $plugin = $component->getPlugin();
        if ($plugin instanceof BlockPluginInterface) {
          $label = $plugin->label();
        }
        else {
          $label = '@todo';
        }

        $build["section-$delta-region-$region-uuid-$uuid"] = [
          'label' => [
            '#markup' => $label . ' (' . $component->getPluginId() . ')',
          ],
          'operations' => [
            '#type' => 'operations',
            '#links' => $this->getComponentOperations($section_storage, $delta, $component),
          ],
        ];
      }
    }


    return $build;
  }

}

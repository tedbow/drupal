<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for moving a block.
 */
class MoveBlockForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The section delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * The region name.
   *
   * @var string
   */
  protected $region;

  /**
   * The component uuid.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The Layout Tempstore.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempStore;

  /**
   * MoveBlockForm constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutTempStore = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_block_move';
  }

  /**
   * Builds move block form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string $uuid
   *   The UUID of the block being updated.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->sectionStorage = $section_storage;
    $this->delta = $delta;
    $this->uuid = $uuid;
    $this->region = $region;

    $form['#attributes']['data-layout-builder-target-highlight-id'] = "block-$uuid";
    $sections = $section_storage->getSections();
    $region_options = [];
    foreach ($sections as $section_delta => $section) {
      $layout = $section->getLayout();
      $layout_definition = $layout->getPluginDefinition();
      $section_label = $this->t('Section: @delta', ['@delta' => $section_delta + 1])->render();
      foreach ($layout_definition->getRegions() as $region_name => $region_info) {
        // Group regions by section.
        $region_options[$section_label]["$section_delta:$region_name"] = $this->t(
          'Section: @delta, Region: @region',
          ['@delta' => $section_delta + 1, '@region' => $region_info['label']]
        );
      }
    }
    $selected_region = $this->getSelectedRegion($form_state);
    $selected_delta = $this->getSelectedDelta($form_state);
    $form['region'] = [
      '#type' => 'select',
      '#options' => $region_options,
      '#title' => $this->t('Region'),
      '#default_value' => "$selected_delta:$selected_region",
      '#ajax' => [
        'wrapper' => 'layout-builder-components-table',
        'callback' => '::getComponentsWrapper',
      ],
    ];

    $current_section = $sections[$selected_delta];
    // Create a wrapping element so that the Ajax update also replaces the
    // 'Show block weights' link.
    $form['components_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'layout-builder-components-table'],
    ];
    $form['components_wrapper']['components'] = [
      '#type' => 'table',
      '#caption' => $this->t('Blocks'),
      '#header' => [
        $this->t('Label'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];
    /** @var \Drupal\layout_builder\SectionComponent[] $components */
    $components = array_filter($current_section->getComponents(), function (SectionComponent $component) use ($selected_region) {
      return $component->getRegion() === $selected_region;
    });
    uasort($components, function (SectionComponent $a, SectionComponent $b) {
      return $a->getWeight() > $b->getWeight() ? 1 : -1;
    });
    // If the component is not in this region add it to the listed components.
    if (!isset($components[$uuid])) {
      $components[$uuid] = $sections[$delta]->getComponent($uuid);
    }
    foreach ($components as $component_uuid => $component) {
      /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
      $plugin = $component->getPlugin();
      $is_current_block = $component_uuid === $uuid;
      if ($is_current_block) {
        // Highlight the current block.
        $label = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $plugin->label(),
          '#attributes' => ['class' => 'current-block'],
        ];
      }
      else {
        $label = ['#markup' => $plugin->label()];
      }
      $row_classes = ['draggable'] + ($is_current_block ? [1 => 'current-block'] : []);
      $form['components_wrapper']['components'][$component->getUuid()] = [
        '#attributes' => ['class' => $row_classes],
        'label' => $label,
        'weight' => [
          '#type' => 'weight',
          '#default_value' => $component->getWeight(),
          '#title' => $this->t('Weight for @block block', ['@block' => $plugin->label()]),
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['table-sort-weight'],
          ],
        ],
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Move'),
      '#button_type' => 'primary',
    ];
    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $region = $this->getSelectedRegion($form_state);
    $delta = $this->getSelectedDelta($form_state);
    $original_section = $this->sectionStorage->getSection($this->delta);
    $component = $original_section->getComponent($this->uuid);
    $section = $this->sectionStorage->getSection($delta);
    if ($delta !== $this->delta) {
      // Remove component from old section and add it to the new section.
      $original_section->removeComponent($this->uuid);
      $section->insertComponent(0, $component);
    }
    $component->setRegion($region);
    foreach ($form_state->getValue('components') as $uuid => $component_info) {
      $section->getComponent($uuid)->setWeight($component_info['weight']);
    }
    $this->layoutTempStore->set($this->sectionStorage);
  }

  /**
   * Ajax callback for the region select element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The components wrapper render array.
   */
  public function getComponentsWrapper(array $form, FormStateInterface $form_state) {
    return $form['components_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

  /**
   * Gets the selected region.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The current region name.
   */
  protected function getSelectedRegion(FormStateInterface $form_state) {
    if ($selected_section_region = $form_state->getValue('region')) {
      return explode(':', $selected_section_region)[1];
    }
    return $this->region;
  }

  /**
   * Gets the selected delta.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int
   *   The section delta.
   */
  protected function getSelectedDelta(FormStateInterface $form_state) {
    if ($selected_section_region = $form_state->getValue('region')) {
      return explode(':', $selected_section_region)[0];
    }
    return (int) $this->delta;
  }

}

<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to choose a new block.
 *
 * @internal
 */
class ChooseBlockController implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use StringTranslationTrait;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * ChooseBlockController constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeRepositoryInterface $entity_type_repository, EntityFieldManagerInterface $entity_field_manager) {
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeRepository = $entity_type_repository;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.repository'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Provides the UI for choosing a new block.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   *
   * @return array
   *   A render array.
   */
  public function build(SectionStorageInterface $section_storage, $delta, $region) {
    $build['#title'] = $this->t('Choose a block');
    if ($this->entityTypeManager->hasDefinition('block_content_type') && $types = $this->entityTypeManager->getStorage('block_content_type')->loadMultiple()) {
      if (count($types) === 1) {
        $type = reset($types);
        $plugin_id = 'inline_block:' . $type->id();
        if ($this->blockManager->hasDefinition($plugin_id)) {
          $url = Url::fromRoute('layout_builder.add_block', [
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
            'region' => $region,
            'plugin_id' => $plugin_id,
          ]);
        }
      }
      else {
        $url = Url::fromRoute('layout_builder.choose_inline_block', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'delta' => $delta,
          'region' => $region,
        ]);
      }
      if (isset($url)) {
        $build['add_block'] = [
          '#type' => 'link',
          '#url' => $url,
          '#title' => $this->t('Create @entity_type', [
            '@entity_type' => $this->entityTypeManager->getDefinition('block_content')->getSingularLabel(),
          ]),
          '#attributes' => $this->getAjaxAttributes(),
        ];
        $build['add_block']['#attributes']['class'][] = 'inline-block-create-button';
      }
    }

    $block_categories['#type'] = 'container';
    $block_categories['#attributes']['class'][] = 'layout-builder-block-categories';

    // @todo Explicitly cast delta to an integer, remove this in
    //   https://www.drupal.org/project/drupal/issues/2984509.
    $delta = (int) $delta;

    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getAvailableContexts($section_storage), [
      'section_storage' => $section_storage,
      'delta' => $delta,
      'region' => $region,
    ]);
    $grouped_definitions = $this->blockManager->getGroupedDefinitions($definitions);
    foreach ($grouped_definitions as $category => $blocks) {
      $block_categories[$category] = $this->buildCategory($section_storage, $delta, $region, $blocks, $category);
    }
    $build['block_categories'] = $block_categories;
    return $build;
  }

  /**
   * Gets the build for category of blocks.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   * @param array $blocks
   *   The blocks for the category.
   * @param string $category
   *   The category label.
   *
   * @return array
   *   The render array for the block category.
   */
  protected function buildCategory(SectionStorageInterface $section_storage, $delta, $region, array $blocks, $category) {
    // Keep track of the weight for field block categories so that these
    // categories can be moved to the top.
    static $field_block_category_weight = -200;

    $is_field_category = $this->isFieldCategory($category);

    $category_build = [
      '#type' => 'details',
      '#title' => $category,
    ];
    if ($is_field_category) {
      // Separate blocks in the field categories into primary and secondary
      // fields. Non-view configurable fields are considered secondary.
      $is_primary_block = function ($block_id) {
        $block_id_parts = explode(':', $block_id);
        if (count($block_id_parts) === 4) {
          list($block_type, $entity_type_id, $bundle, $field_name) = $block_id_parts;
          if ($block_type === 'field_block') {
            $field_definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name];
            return $field_definition->isDisplayConfigurable('view');
          }
        }
        return TRUE;
      };

      $block_ids = array_keys($blocks);
      $primary_block_ids = array_filter($block_ids, $is_primary_block);
      $secondary_block_ids = array_diff($block_ids, $primary_block_ids);
      $primary_block_links = $this->getBlockLinks($section_storage, $delta, $region, array_intersect_key($blocks, array_flip($primary_block_ids)));
      $secondary_block_links = $this->getBlockLinks($section_storage, $delta, $region, array_intersect_key($blocks, array_flip($secondary_block_ids)));

      // If there are primary links move the category to the top and open it.
      if ($primary_block_links['#links']) {
        $category_build['links'] = $primary_block_links;
        $category_build['#open'] = TRUE;
        $category_build['#weight'] = $field_block_category_weight;
        $field_block_category_weight += 10;
      }

      if ($secondary_block_links['#links']) {
        if (!$primary_block_links['#links']) {
          // If no other links exist set these links as top level links for the
          // category.
          $category_build['links'] = $secondary_block_links;
        }
        else {
          $category_build['more_fields'] = [
            '#type' => 'details',
            '#title' => $this->t('More'),
            '#open' => FALSE,
            'links' => $secondary_block_links,
            '#attributes' => [
              'class' => ['layout-builder-secondary-blocks'],
            ],
          ];
        }
      }
    }
    else {
      $category_build['links'] = $this->getBlockLinks($section_storage, $delta, $region, $blocks);
    }
    return $category_build;
  }

  /**
   * Determines if a block category is field block category.
   *
   * @param string $category
   *   The block category.
   *
   * @return bool
   *   TRUE if the category is a field block category.
   */
  protected function isFieldCategory($category) {
    static $entity_field_categories = [];
    if (empty($entity_field_categories)) {
      foreach ($this->entityTypeRepository->getEntityTypeLabels() as $entity_type_label) {
        $entity_field_categories[] = (string) $this->t('@entity fields', ['@entity' => $entity_type_label]);
      }
    }
    return in_array($category, $entity_field_categories, TRUE);
  }

  /**
   * Provides the UI for choosing a new inline block.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   *
   * @return array
   *   A render array.
   */
  public function inlineBlockList(SectionStorageInterface $section_storage, $delta, $region) {
    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getAvailableContexts($section_storage), [
      'section_storage' => $section_storage,
      'region' => $region,
      'list' => 'inline_blocks',
    ]);
    $blocks = $this->blockManager->getGroupedDefinitions($definitions);
    $build = [];
    if (isset($blocks['Inline blocks'])) {
      $build['links'] = $this->getBlockLinks($section_storage, $delta, $region, $blocks['Inline blocks']);
      $build['links']['#attributes']['class'][] = 'inline-block-list';
      foreach ($build['links']['#links'] as &$link) {
        $link['attributes']['class'][] = 'inline-block-list__item';
      }
      $build['back_button'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('layout_builder.choose_block',
          [
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
            'region' => $region,
          ]
        ),
        '#title' => $this->t('Back'),
        '#attributes' => $this->getAjaxAttributes(),
      ];
    }
    return $build;
  }

  /**
   * Gets a render array of block links.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   * @param array $blocks
   *   The information for each block.
   *
   * @return array
   *   The block links render array.
   */
  protected function getBlockLinks(SectionStorageInterface $section_storage, $delta, $region, array $blocks) {
    $links = [];
    foreach ($blocks as $block_id => $block) {
      $link = [
        'title' => $block['admin_label'],
        'url' => Url::fromRoute('layout_builder.add_block',
          [
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
            'region' => $region,
            'plugin_id' => $block_id,
          ]
        ),
        'attributes' => $this->getAjaxAttributes(),
      ];

      $links[] = $link;
    }
    return [
      '#theme' => 'links',
      '#links' => $links,
    ];
  }

  /**
   * Get dialog attributes if an ajax request.
   *
   * @return array
   *   The attributes array.
   */
  protected function getAjaxAttributes() {
    if ($this->isAjax()) {
      return [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ];
    }
    return [];
  }

}

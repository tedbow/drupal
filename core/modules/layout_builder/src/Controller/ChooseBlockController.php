<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * ChooseBlockController constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   */
  public function __construct(BlockManagerInterface $block_manager, EntityTypeRepositoryInterface $entity_type_repository) {
    $this->blockManager = $block_manager;
    $this->entityTypeRepository = $entity_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('entity_type.repository')
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
    $entity_type_labels = $this->entityTypeRepository->getEntityTypeLabels();
    $build['#type'] = 'container';
    $build['#attributes']['class'][] = 'block-categories';

    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getAvailableContexts($section_storage), [
      'section_storage' => $section_storage,
      'region' => $region,
    ]);
    $field_block_category_weight = -200;
    foreach ($this->blockManager->getGroupedDefinitions($definitions) as $category => $blocks) {
      $build[$category]['#type'] = 'details';
      $build[$category]['#title'] = $category;

      $build[$category]['links'] = [
        '#theme' => 'links',
      ];
      $non_view_configurable_field_links = [];
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
        ];
        if ($this->isAjax()) {
          $link['attributes']['class'][] = 'use-ajax';
          $link['attributes']['data-dialog-type'][] = 'dialog';
          $link['attributes']['data-dialog-renderer'][] = 'off_canvas';
        }
        if ($block['id'] === 'field_block' && empty($block['_is_view_configurable'])) {
          $non_view_configurable_field_links[] = $link;
        }
        else {
          $links[] = $link;
        }
      }
      $build[$category]['links']['#links'] = $links;
      if ($non_view_configurable_field_links) {
        if (empty($links)) {
          // If no other links exist add these links as top level links for the
          // category.
          $build[$category]['links']['#links'] = $non_view_configurable_field_links;
        }
        else {
          $build[$category]['more_fields'] = [
            '#type' => 'details',
            '#title' => $this->t('More+'),
            '#open' => FALSE,
            'links' => [
              '#theme' => 'links',
              '#links' => $non_view_configurable_field_links,
            ],
          ];
        }
      }
      // If this a entity category and there are links besides non 'view'
      // configurable field blocks move the category to the top and open it.
      if (in_array($category, $entity_type_labels) && $links) {
        $build[$category]['#open'] = TRUE;
        $build[$category]['#weight'] = $field_block_category_weight;
        $field_block_category_weight += 10;
      }
    }
    return $build;
  }

}

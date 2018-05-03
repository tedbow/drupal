<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * ChooseBlockController constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager')
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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function build(SectionStorageInterface $section_storage, $delta, $region) {
    if ($this->entityTypeManager->hasDefinition('block_content_type') && $types = $this->entityTypeManager->getStorage('block_content_type')->loadMultiple()) {
      if (count($types) === 1) {
        $type = reset($types);
        $plugin_in = 'inline_block_content:' . $type->id();
        if ($this->blockManager->hasDefinition($plugin_in)) {
          $url = Url::fromRoute('layout_builder.add_block', [
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
            'region' => $region,
            'plugin_id' => $plugin_in,
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
      $build['add_block'] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $this->t('Add new Block'),
        '#attributes' => $this->getAjaxAttributes(),
      ];
      $build['add_block']['#attributes']['class'][] = 'button';
    }

    $block_categories['#type'] = 'container';
    $block_categories['#attributes']['class'][] = 'block-categories';

    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getAvailableContexts($section_storage), [
      'section_storage' => $section_storage,
      'region' => $region,
    ]);

    $grouped_definitions = $this->blockManager->getGroupedDefinitions($definitions);
    unset($grouped_definitions['Create new block']);
    foreach ($grouped_definitions as $category => $blocks) {
      $block_categories[$category]['#type'] = 'details';
      $block_categories[$category]['#open'] = TRUE;
      $block_categories[$category]['#title'] = $category;
      $block_categories[$category]['links'] = $this->getBlockLinks($section_storage, $delta, $region, $blocks);
    }
    $build['block_categories'] = $block_categories;
    return $build;
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
    if (isset($blocks['Create new block'])) {
      return $this->getBlockLinks($section_storage, $delta, $region, $blocks['Create new block']);
    }
    return [];
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

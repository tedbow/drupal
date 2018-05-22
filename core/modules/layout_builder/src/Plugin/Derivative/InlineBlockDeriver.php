<?php

namespace Drupal\layout_builder\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves block plugin definitions for all inline block types.
 */
class InlineBlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BlockContentDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    if ($this->entityTypeManager->hasDefinition('inline_block_type')) {
      $inline_block_types = $this->entityTypeManager->getStorage('inline_block_type')->loadMultiple();
      foreach ($inline_block_types as $id => $type) {
        $this->derivatives[$id] = $base_plugin_definition;
        $this->derivatives[$id]['admin_label'] = $type->label();
        $this->derivatives[$id]['config_dependencies'][$type->getConfigDependencyKey()][] = $type->getConfigDependencyName();
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}

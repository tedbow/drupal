<?php

namespace Drupal\layout_builder\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves block plugin definitions for all custom block types.
 */
class InlineBlockContentDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

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
    if ($this->entityTypeManager->hasDefinition('block_content_type')) {
      $block_content_types = $this->entityTypeManager->getStorage('block_content_type')->loadMultiple();
      foreach ($block_content_types as $id => $type) {
       // foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
        //  if ($entity_type_id === 'entity_view_display' || in_array(FieldableEntityInterface::class, class_implements($definition->getClass()))) {
            $derivative_id = $id . PluginBase::DERIVATIVE_SEPARATOR . $entity_type_id;
            $derivative_id = $id;
            $this->derivatives[$derivative_id] = $base_plugin_definition;
            $this->derivatives[$derivative_id]['admin_label'] = $type->label();
            $this->derivatives[$derivative_id]['config_dependencies'][$type->getConfigDependencyKey()][] = $type->getConfigDependencyName();
            /*$this->derivatives[$derivative_id]['context'] = [
              'entity' => new ContextDefinition('entity:' . $entity_type_id, $definition->getLabel(), TRUE),
            ];*/

          //}

        //}

      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}

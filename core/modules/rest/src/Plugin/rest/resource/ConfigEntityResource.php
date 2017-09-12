<?php

namespace Drupal\rest\Plugin\rest\resource;

use Drupal\Core\Entity\EntityInterface;

/**
 * Specific config entity resource with special behaviour for validation.
 */
class ConfigEntityResource extends EntityResource {

  /**
   * {@inheritdoc}
   */
  protected function validate(EntityInterface $entity) {
    // Use typed config to validate the validate the config entity.
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $type_config_manager */
    $type_config_manager = \Drupal::service('config.typed');
    $typed_config = $type_config_manager->createFromNameAndData($entity->getConfigDependencyName(), $entity->toArray());
    $violations = $typed_config->validate();

    $this->processViolations($violations);
  }

}

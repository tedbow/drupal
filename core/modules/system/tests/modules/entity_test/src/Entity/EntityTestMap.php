<?php

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines a test class for testing map field normalization.
 *
 * @ContentEntityType(
 *   id = "entity_test_map",
 *   label = @Translation("Test normalizing map fields"),
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "label" = "name"
 *   },
 *   base_table = "entity_test_map",
 *   persistent_cache = FALSE,
 * )
 */
class EntityTestMap extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['map'] = BaseFieldDefinition::create('map')
      ->setLabel(t('map'));

    return $fields;
  }

}

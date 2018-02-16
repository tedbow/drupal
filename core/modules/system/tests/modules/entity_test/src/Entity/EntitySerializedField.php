<?php

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines a test class for testing fields with a serialized column.
 *
 * @ContentEntityType(
 *   id = "entity_test_serialized_field",
 *   label = @Translation("Test serialized fields"),
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "label" = "name"
 *   },
 *   base_table = "entity_test_serialized_fields",
 *   persistent_cache = FALSE,
 * )
 */
class EntitySerializedField extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['serialized'] = BaseFieldDefinition::create('serialized_item_test')
      ->setLabel(t('Serialized'));

    return $fields;
  }

}

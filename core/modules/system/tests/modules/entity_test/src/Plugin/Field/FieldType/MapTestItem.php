<?php

namespace Drupal\entity_test\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines the 'map_test' field type.
 *
 * @FieldType(
 *   id = "map_test",
 *   label = @Translation("Map Test"),
 *   description = @Translation("Another dummy field type."),
 * )
 */
class MapTestItem extends MapItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'value' => MapDataDefinition::create()->setLabel(t('Freeform')),
    ];
  }

}

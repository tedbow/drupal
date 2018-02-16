<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\Field\FieldItemInterface;

/**
 * A trait providing methods for serialized columns.
 */
trait SerializedColumnNormalizerTrait {

  /**
   * Checks if there is a serialized string for a column.
   *
   * @param mixed $data
   *   The data to denormalize.
   * @param string $class
   *   The expected class to instantiate.
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   The field item.
   */
  protected function checkForSerializedStrings($data, $class, FieldItemInterface $field_item) {
    // Require specialized denormalizers for fields with 'serialize' columns.
    // Note: this cannot be checked in ::supportsDenormalization() because at
    //       that time we only have the field item class. ::hasSerializeColumn()
    //       must be able to call $field_item->schema(), which requires a field
    //       storage definition. To determine that, the entity type and bundle
    //       must be known, which is contextual information that the Symfony
    //       serializer does not pass to ::supportsDenormalization().
    if ($this->dataHasStringForSerializeColumn($field_item, $data)) {
      throw new \LogicException(sprintf('The generic FieldItemNormalizer cannot denormalize string values for "%s" properties of "%s" fields (field item class: %s).', implode('", "', $this->getSerializedPropertyNames($field_item)), $field_item->getPluginDefinition()['id'], $class));
    }
  }

  /**
   * Checks if the data contains string value for serialize column.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   The field item.
   * @param array $data
   *   The data being denormalized.
   *
   * @return bool
   *   TRUE if there is a string value for serialize column, otherwise FALSE.
   */
  protected function dataHasStringForSerializeColumn(FieldItemInterface $field_item, array $data) {
    foreach ($this->getSerializedPropertyNames($field_item) as $property_name) {
      if (isset($data[$property_name]) && is_string($data[$property_name])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the names of all serialized properties.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   The field item.
   *
   * @return string[]
   *   The property names for serialized properties.
   */
  protected function getSerializedPropertyNames(FieldItemInterface $field_item) {
    $field_storage_definition = $field_item->getFieldDefinition()->getFieldStorageDefinition();
    $field_storage_schema = $field_item->schema($field_storage_definition);
    // If there are no columns then there are no serialized properties.
    if (!isset($field_storage_schema['columns'])) {
      return [];
    }
    $serialized_columns = array_filter($field_storage_schema['columns'], function ($column_schema) {
      return isset($column_schema['serialize']) && $column_schema['serialize'] === TRUE;
    });
    return array_keys($serialized_columns);
  }

}

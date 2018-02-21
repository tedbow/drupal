<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;

/**
 * @internal
 */
class MapItemNormalizer extends FieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = MapItem::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    // Remove the level of indirection: pretend the arbitrary keys stored in the
    // map are the properties on this field.
    return parent::normalize($field_item, $format, $context)['value'];
  }

  /**
   * {@inheritdoc}
   */
  protected function constructValue($data, $context) {
    // Prepare for storage: all received data must be assigned to this field
    // type's sole property: 'value'.
    return ['value' => $data];
  }

}

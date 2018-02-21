<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Converts propertyless Map objects into arrays.
 *
 * This normalizer only supports Map objects that do not have have property
 * definitions.
 */
class PropertylessMapNormalizer extends TypedDataNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = Map::class;

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    /* @var \Drupal\Core\TypedData\Plugin\DataType\Map $data  */
    if (parent::supportsNormalization($data, $format)) {
      $definition = $data->getDataDefinition();
      if ($definition instanceof ComplexDataDefinitionInterface && empty($definition->getPropertyDefinitions())) {
        // Map objects without properties defined must be treated specially: the
        // top-level keys stored must be considered the properties during
        // normalization. The parent ::normalize() method does this.
        return TRUE;
      }
      else {
        // Map objects with properties defined can be handled by
        // \Drupal\serialization\Normalizer\ComplexDataNormalizer::normalize().
        return FALSE;
      }
    }
    return FALSE;
  }

}

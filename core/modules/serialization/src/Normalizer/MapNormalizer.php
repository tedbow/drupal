<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Converts Map objects into arrays.
 *
 * This normalizer only supports Map objects that do not have have property
 * definitions.
 */
class MapNormalizer extends TypedDataNormalizer {

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
        return TRUE;
      }
    }
    return FALSE;
  }

}

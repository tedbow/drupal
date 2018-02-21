<?php

namespace Drupal\Tests\rest\Functional\EntityResource\EntityTest;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * Test that MapItem are correctly exposed in REST.
 *
 * @group rest
 */
class EntityTestMapItemNormalizerTest extends EntityTestResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  protected static $mapValue = [
    'key1' => 'value',
    'key2' => 'no, val you',
    'nested' => [
      'bird' => 'robin',
      'doll' => 'Russian',
    ],
  ];


  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $expected = parent::getExpectedNormalizedEntity();
    // The 'non_exposed_value' property in test field type will not return in
    // normalization because setExposed(TRUE) was not called for this property.
    // @see \Drupal\entity_test\Plugin\Field\FieldType\ExposedPropertyTestFieldItem::propertyDefinitions
    $expected['field_map'] = [
      static::$mapValue
    ];
    return $expected;
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    if (!FieldStorageConfig::loadByName('entity_test', 'field_map')) {
      FieldStorageConfig::create([
        'entity_type' => 'entity_test',
        'field_name' => 'field_map',
        'type' => 'map',
        'cardinality' => 1,
        'translatable' => FALSE,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'entity_test',
        'field_name' => 'field_map',
        'bundle' => 'entity_test',
        'label' => 'Test field with map property',
      ])->save();
    }

    $entity = parent::createEntity();
    $entity->field_map = [
      'value' => static::$mapValue,
    ];
    $entity->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return parent::getNormalizedPostEntity() + [
      'field_map' => [
        static::$mapValue,
      ],
    ];
  }

}

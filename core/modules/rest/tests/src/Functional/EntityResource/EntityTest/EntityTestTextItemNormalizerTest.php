<?php

namespace Drupal\Tests\rest\Functional\EntityResource\EntityTest;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * @group rest
 */
class EntityTestTextItemNormalizerTest extends EntityTestResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $expected = parent::getExpectedNormalizedEntity();
    $expected['field_test_text'] = [
      [
        'value' => 'Cádiz is the oldest continuously inhabited city in Spain and a nice place to spend a Sunday with friends.',
        'format' => 'plain_text',
        'processed' => '<p>Cádiz is the oldest continuously inhabited city in Spain and a nice place to spend a Sunday with friends.</p>' . "\n",
      ],
    ];
    return $expected;
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $entity = parent::createEntity();
    $entity->field_test_text = [
      'value' => 'Cádiz is the oldest continuously inhabited city in Spain and a nice place to spend a Sunday with friends.',
      'format' => 'plain_text',
    ];
    $entity->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    $post_entity = parent::getNormalizedPostEntity();
    $post_entity['field_test_text'] = [
      [
        'value' => 'Llamas are awesome.',
        'format' => 'plain_text',
      ],
    ];
    return $post_entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags() {
    return Cache::mergeTags(['config:filter.format.plain_text'], parent::getExpectedCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return Cache::mergeContexts(['languages:language_interface', 'theme'], parent::getExpectedCacheContexts());
  }

}

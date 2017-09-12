<?php

namespace Drupal\Tests\rest\Functional\EntityResource\ConfigTest;

use Drupal\config_test\Entity\ConfigTest;
use Drupal\Core\Url;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use GuzzleHttp\RequestOptions;

abstract class ConfigTestResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['config_test', 'config_test_rest'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'config_test';

  /**
   * @var \Drupal\config_test\ConfigTestInterface
   */
  protected $entity;

  /**
   * Counter used internally to have deterministic IDs.
   *
   * @var int
   */
  protected $counter = 0;

  /**
   * {@inheritdoc}
   */
  protected static $firstCreatedEntityId = 'llama1';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['view config_test']);
        break;
      case 'POST':
      case 'PATCH':
      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer config_test']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $config_test = ConfigTest::create([
      'id' => 'llama' . (string) $this->counter,
      'label' => 'Llama',
    ]);
    $config_test->save();

    return $config_test;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $normalization = [
      'uuid' => $this->entity->uuid(),
      'id' => 'llama' . (string) $this->counter,
      'weight' => 0,
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'label' => 'Llama',
      'style' => NULL,
      'size' => NULL,
      'size_value' => NULL,
      'protected_property' => NULL,
    ];

    return $normalization;
  }

  /**
   * {@inheritdoc}
   */
  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options) {
    parent::assertNormalizationEdgeCases($method, $url, $request_options);

    $normalization = $this->getNormalizedPostEntity();
    $normalization['protected_property'] = 'some value';
    $request_options[RequestOptions::BODY] = $this->serializer->encode($normalization, static::$format);
    $response = $this->request($method, $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nprotected_property: Protected property cannot be changed.\n", $response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity($count_up = TRUE) {
    if ($count_up) {
      $this->counter++;
    }

    return [
      'id' => 'llama' . (string) $this->counter,
      'label' => 'Llamam',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPatchEntity() {
    return $this->getNormalizedPostEntity(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    if ($this->config('rest.settings')->get('bc_entity_resource_permissions')) {
      return parent::getExpectedUnauthorizedAccessMessage($method);
    }

    if ($method === 'GET') {
      return 'You are not authorized to view this config_test entity.';
    }
    return "The 'administer config_test' permission is required.";
  }

}

<?php

namespace Drupal\rest_test\Plugin\rest\resource;

use Drupal\Core\Plugin\ResourceBase;
use Drupal\Core\Response\ResourceResponse;

/**
 * Class used to test that serialization_class is optional.
 *
 * @RestResource(
 *   id = "serialization_test",
 *   label = @Translation("Optional serialization_class"),
 *   serialization_class = "",
 *   uri_paths = {}
 * )
 */
class NoSerializationClassTestResource extends ResourceBase {

  /**
   * Responds to a POST request.
   *
   * @param array $data
   *   An array with the payload.
   *
   * @return \Drupal\Core\Response\ResourceResponse
   */
  public function post(array $data) {
    return new ResourceResponse($data);
  }

}

<?php

namespace Drupal\update_test\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a controller to return JSON for security advisory tests.
 */
class PsaTestController {

  /**
   * Gets the contents of JSON file.
   *
   * @param string $json_name
   *   The name of the JSON file without the file extension.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   The test response.
   */
  public function getPsaJson(string $json_name) {
    $file = __DIR__ . "/../../../../fixtures/psa_feed/$json_name.json";
    $headers = ['Content-Type' => 'application/json; charset=utf-8'];
    if (!is_file($file)) {
      // Return an empty response.
      return new Response('', 404, $headers);
    }
    $contents = file_get_contents($file);
    $contents = str_replace('[CORE_VERSION]', \Drupal::VERSION, $contents);
    return new JsonResponse($contents, 200, $headers, TRUE);
  }

}

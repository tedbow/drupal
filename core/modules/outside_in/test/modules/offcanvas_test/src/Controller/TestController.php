<?php

namespace Drupal\offcanvas_test\Controller;

/**
 * Test controller for 2 different responses.
 */
class TestController {

  /**
   * Thing1.
   *
   * @return string
   *   Return Hello string.
   */
  public function thing1() {
    return [
      '#type' => 'markup',
      '#markup' => 'Thing 1 says hello',
    ];
  }
  /**
   * Thing2.
   *
   * @return string
   *   Return Hello string.
   */
  public function thing2() {
    return [
      '#type' => 'markup',
      '#markup' => 'Thing 2 says hello',
    ];
  }

}

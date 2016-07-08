<?php

namespace Drupal\block\Controller;

/**
 * Class Hello
 *
 * @package Drupal\block\Controller
 */
class Hello {

  public function world() {
    return [
      '#type' => 'markup',
      '#markup' => 'hello world!',
    ];
  }
}

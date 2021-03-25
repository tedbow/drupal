<?php

namespace Drupal\auto_updates;

/**
 * Defines a service that calculates available updates for core.
 */
class UpdateCalculator {

  /**
   * Gets the support update version for core, if any.
   */
  public function getSupportedUpdateVersion() {
    module_load_include('inc', 'update');
  }



}

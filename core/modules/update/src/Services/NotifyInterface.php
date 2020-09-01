<?php

namespace Drupal\update\Services;

/**
 * Interface NotifyInterface.
 *
 * Implementor class can extend this iterface to implement the method(s) defined here.
 */
interface NotifyInterface {

  /**
   * Send notification when PSAs are available.
   */
  public function send();

}

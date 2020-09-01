<?php

namespace Drupal\update\Psa;

/**
 * Defines an interface for sending notification of update PSA's.
 */
interface NotifyInterface {

  /**
   * Send notification when PSAs are available.
   */
  public function send();

}

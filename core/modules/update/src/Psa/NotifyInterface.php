<?php

namespace Drupal\update\Psa;

/**
 * Defines an interface for sending notification of update PSAs.
 */
interface NotifyInterface {

  /**
   * Send notification when PSAs are available.
   */
  public function send();

}

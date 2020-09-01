<?php

namespace Drupal\update\Psa;

/**
 * Defines an interface to get Public Service Messages.
 */
interface UpdatesPsaInterface {

  /**
   * Gets public service messages.
   *
   * @return array
   *   A array of translatable strings.
   */
  public function getPublicServiceMessages();

}

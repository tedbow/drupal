<?php

namespace Drupal\update\Services;

/**
 * Interface UpdatesPsaInterface.
 *
 * Interface to get Publich Service Messages
 */
interface UpdatesPsaInterface {

  /**
   * Get public service messages.
   *
   * @return array
   *   A return of translatable strings.
   */
  public function getPublicServiceMessages();

}

<?php

namespace Drupal\layout_builder;

interface TranslatedLayoutStorageInterface {

  public function setComponentConfiguration($uuid, $configuration);

  /**
   * @param $uuid
   *
   * @return array
   *   The component configuration.
   */
  public function getComponentConfiguration($uuid);

}

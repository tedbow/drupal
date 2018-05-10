<?php

namespace Drupal\layout_builder\Plugin\Block;

/**
 * Provides a interface for plugins that have an extra step for saving.
 *
 * @todo Move this interface out of Layout Builder module.
 *
 * @todo better name.
 */
interface PermanentSavePluginInterface {

  /**
   * Saves the plugin permanently.
   */
  public function savePermanently();
}
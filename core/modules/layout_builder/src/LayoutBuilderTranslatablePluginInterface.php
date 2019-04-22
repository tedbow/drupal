<?php

namespace Drupal\layout_builder;

/**
 * Provides an interface for plugins with Layout Builder translatable settings.
 *
 * @package Drupal\layout_builder
 */
interface LayoutBuilderTranslatablePluginInterface {

  /**
   * Determines whether the plugin has translatable configuration.
   *
   * @return bool
   *   TRUE if the plugin currently has configuration that can be translated.
   */
  public function hasTranslatableConfiguration();

}

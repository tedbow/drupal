<?php

namespace Drupal\layout_builder;

/**
 * An interface for layout builder plugin translation forms.
 */
interface LayoutBuilderPluginTranslationFormInterface {

  /**
   * Sets the plugin translated configuration.
   *
   * @param array $translated_configuration
   *   The translated configuration.
   */
  public function setTranslatedConfiguration(array $translated_configuration);

}

<?php

namespace Drupal\layout_builder;

/**
 * Defines an interface for translatable section overrides.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
interface TranslatableSectionStorageInterface {

  /**
   * Indicates if the layout is translatable.
   *
   * @return bool
   *   TRUE if the layout is translatable, otherwise FALSE.
   */
  public function isTranslatable();

  /**
   * Indicates if the layout is default translation layout.
   *
   * @return bool
   *   TRUE if the layout is the default translation layout, otherwise FALSE.
   */
  public function isDefaultTranslation();

  /**
   * Sets the translated component configuration.
   *
   * @param string $uuid
   * @param array $configuration
   */
  public function setTranslatedComponentConfiguration($uuid, array $configuration);

  /**
   * Gets the translated component configuration.
   *
   * @param string $uuid
   *
   * @return array
   *   The component configuration.
   */
  public function getTranslatedComponentConfiguration($uuid);

  public function getTranslatedSections();

  public function getTranslatedConfiguration();

  /**
   * Saves translated configuration.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function saveTranslatedConfiguration($langcode);

}

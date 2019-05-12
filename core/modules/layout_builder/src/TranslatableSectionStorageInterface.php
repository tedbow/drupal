<?php

namespace Drupal\layout_builder;

/**
 * Defines an interface for translatable section overrides.
 */
interface TranslatableSectionStorageInterface extends SectionStorageInterface {

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
   *   The component UUID.
   * @param array $configuration
   *   The component's translated configuration.
   */
  public function setTranslatedComponentConfiguration($uuid, array $configuration);

  /**
   * Gets the translated component configuration.
   *
   * @param string $uuid
   *   The component UUID.
   *
   * @return array
   *   The component's translated configuration.
   */
  public function getTranslatedComponentConfiguration($uuid);

  /**
   * Get the translated configuration for the layout.
   *
   * @return array
   *   The translated configuration for the layout.
   */
  public function getTranslatedConfiguration();

}

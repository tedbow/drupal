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
   * Gets the default translation section storage.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage used by the default translation.
   */
  public function getDefaultTranslationSectionStorage();

  /**
   * Gets the layout default translation sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   A sequentially and numerically keyed array of section objects.
   */
  public function getDefaultTranslationSections();

}

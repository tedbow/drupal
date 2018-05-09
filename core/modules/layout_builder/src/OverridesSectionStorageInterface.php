<?php

namespace Drupal\layout_builder;

/**
 * Defines an interface for an object that stores layout sections for overrides.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
interface OverridesSectionStorageInterface extends SectionStorageInterface {

  /**
   * Returns the corresponding defaults section storage for this override.
   *
   * @return \Drupal\layout_builder\DefaultsSectionStorageInterface
   *   The defaults section storage.
   *
   * @todo Determine if this method needs a parameter in
   *   https://www.drupal.org/project/drupal/issues/2936507.
   */
  public function getDefaultSectionStorage();

  /**
   * Duplicate defaults inline custom blocks.
   *
   * @param \Drupal\layout_builder\Section[]
   *   A sequentially and numerically keyed array of section objects.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   *
   * @return \Drupal\layout_builder\Section[]
   *   A sequentially and numerically keyed array of section objects.
   */
  public function duplicateDefaultsInlineCustomBlocks($sections);

}

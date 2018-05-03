<?php

namespace Drupal\layout_builder;

/**
 * Defines an interface for an object that stores legacy data for defaults.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
interface LegacyDefaultsSectionListInterface {

  /**
   * Sets the display options for a legacy component.
   *
   * Since ::setComponent() includes it's own backwards compatibility layer,
   * calling ::setComponent() will result in an infinite loop. Use this method
   * to avoid the loop when providing additional backwards compatibility layers.
   *
   * @param string $name
   *   The name of the component.
   * @param array $options
   *   The display options.
   *
   * @return $this
   */
  public function setLegacyComponent($name, array $options);

}

<?php

namespace Drupal\outside_in\Block;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides form discovery capabilities for block plugins.
 */
interface OutsideInBlockManagerInterface {

  /**
   * Creates a new form instance.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin the form is for.
   * @param string $operation
   *   The name of the operation to use, e.g., 'default'.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   A plugin form instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getFormObject(PluginInspectionInterface $plugin, $operation);

}

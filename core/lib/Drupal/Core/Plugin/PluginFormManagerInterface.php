<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for retrieving form objects for plugins.
 */
interface PluginFormManagerInterface {

  /**
   * Creates a new form instance.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin the form is for.
   * @param string $operation
   *   The name of the operation to use, e.g., 'add' or 'edit'.
   * @param string $fallback_operation
   *   (optional) The name of the fallback operation to use.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   A plugin form instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getFormObject(PluginInspectionInterface $plugin, $operation, $fallback_operation = NULL);

}

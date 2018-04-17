<?php

namespace Drupal\Core\Plugin\Context;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Provides an interface for plugin managers that support context-aware plugins.
 */
interface ContextAwarePluginManagerInterface extends PluginManagerInterface {

  /**
   * Determines plugins whose constraints are satisfied by a set of contexts.
   *
   * @todo Use context definition objects after
   *   https://www.drupal.org/node/2281635.
   *
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts.
   *
   * @return array
   *   An array of plugin definitions.
   *
   * @deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0.
   *   Instead use \Drupal\Core\Plugin\PluginDefinitionFiltererInterface.
   */
  public function getDefinitionsForContexts(array $contexts = []);

}

<?php

namespace Drupal\Core\Plugin\Context;

/**
 * Provides a trait for plugin managers that support context-aware plugins.
 *
 * @deprecated in Drupal 8.6.0 and will be removed before Drupal 9.0.0.
 *   Instead use \Drupal\Core\Plugin\PluginDefinitionFiltererInterface.
 */
trait ContextAwarePluginManagerTrait {

  /**
   * Wraps the context handler.
   *
   * @return \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected function contextHandler() {
    return \Drupal::service('context.handler');
  }

  /**
   * See \Drupal\Core\Plugin\Context\ContextAwarePluginManagerInterface::getDefinitionsForContexts().
   */
  public function getDefinitionsForContexts(array $contexts = []) {
    return $this->contextHandler()->filterPluginDefinitionsByContexts($contexts, $this->getDefinitions());
  }

  /**
   * See \Drupal\Component\Plugin\Discovery\DiscoveryInterface::getDefinitions().
   */
  abstract public function getDefinitions();

}

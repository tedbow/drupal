<?php

namespace Drupal\Core\Plugin;

/**
 * Provides a trait for plugin managers that allow filtering plugin definitions.
 */
trait FilteredPluginManagerTrait {

  /**
   * Implements \Drupal\Core\Plugin\FilteredPluginManagerInterface::getFilteredDefinitions().
   */
  public function getFilteredDefinitions($consumer, $contexts = NULL, array $extra = []) {
    $definitions = $this->getDefinitions();
    if (!is_null($contexts)) {
      $definitions = $this->contextHandler()->filterPluginDefinitionsByContexts($contexts, $definitions);
    }

    $type = $this->getType();
    $hooks = [];
    $hooks[] = "plugin_filter_{$type}";
    $hooks[] = "plugin_filter_{$type}__{$consumer}";
    $this->moduleHandler()->alter($hooks, $definitions, $extra, $consumer);
    $this->themeManager()->alter($hooks, $definitions, $extra, $consumer);
    return $definitions;
  }

  /**
   * A string identifying the plugin type.
   *
   * This string should be unique and generally will correspond to the string
   * used by the discovery, e.g. the annotation class or the YAML file name.
   *
   * @return string
   *   A string identifying the plugin type.
   */
  abstract protected function getType();

  /**
   * Wraps the context handler.
   *
   * @return \Drupal\Core\Plugin\Context\ContextHandlerInterface
   *   The context handler.
   */
  protected function contextHandler() {
    return \Drupal::service('context.handler');
  }

  /**
   * Wraps the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  protected function moduleHandler() {
    return \Drupal::service('module_handler');
  }

  /**
   * Wraps the theme manager.
   *
   * @return \Drupal\Core\Theme\ThemeManagerInterface
   *   The theme manager.
   */
  protected function themeManager() {
    return \Drupal::service('theme.manager');
  }

  /**
   * See \Drupal\Component\Plugin\Discovery\DiscoveryInterface::getDefinitions().
   */
  abstract public function getDefinitions();

}

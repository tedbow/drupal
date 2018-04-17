<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Provides methods to retrieve filtered plugin definitions.
 *
 * This allows modules and themes to filter plugin definitions, which is useful
 * for tasks like hiding definitions from user interfaces.
 *
 * @see hook_plugin_filter_TYPE_alter()
 * @see hook_plugin_filter_TYPE__CONSUMER_alter()
 */
class PluginDefinitionFilterer implements PluginDefinitionFiltererInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * Constructs a new PluginDefinitionFilterer.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager, ContextHandlerInterface $context_handler) {
    $this->moduleHandler = $module_handler;
    $this->themeManager = $theme_manager;
    $this->contextHandler = $context_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function get($type, $consumer, DiscoveryInterface $discovery, $contexts = NULL, array $extra = []) {
    $definitions = $discovery->getDefinitions();
    if (!is_null($contexts)) {
      $definitions = $this->contextHandler->filterPluginDefinitionsByContexts($contexts, $definitions);
    }

    $hooks = [];
    $hooks[] = "plugin_filter_{$type}";
    $hooks[] = "plugin_filter_{$type}__{$consumer}";
    $this->moduleHandler->alter($hooks, $definitions, $extra, $consumer);
    $this->themeManager->alter($hooks, $definitions, $extra, $consumer);
    return $definitions;
  }

}

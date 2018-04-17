<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;

/**
 * Provides methods to retrieve filtered plugin definitions.
 *
 * This allows modules and themes to filter plugin definitions, which is useful
 * for tasks like hiding definitions from user interfaces.
 *
 * @see hook_plugin_filter_TYPE_alter()
 * @see hook_plugin_filter_TYPE__CONSUMER_alter()
 */
interface PluginDefinitionFiltererInterface {

  /**
   * Gets the plugin definitions for a given type and consumer and filters them.
   *
   * @param string $type
   *   A string identifying the plugin type.
   * @param string $consumer
   *   A string identifying the consumer of these plugin definitions.
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The plugin discovery, usually the plugin manager.
   * @param \Drupal\Component\Plugin\Context\ContextInterface[]|null $contexts
   *   (optional) Either an array of contexts to use for filtering, or NULL to
   *   not filter by contexts.
   * @param mixed[] $extra
   *   (optional) An associative array containing additional information
   *   provided by the code requesting the filtered definitions.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[]
   *   An array of plugin definitions that are filtered.
   */
  public function get($type, $consumer, DiscoveryInterface $discovery, $contexts = NULL, array $extra = []);

}

<?php

namespace Drupal\Core\Http;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Provides a plugin manager for link relationships.
 */
class LinkRelationManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'class' => LinkRelation::class,
  ];

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * {@inheritdoc}
   */
  public function __construct($root, ModuleHandlerInterface $module_handler) {
    $this->root = $root;
    $this->pluginInterface = LinkRelationInterface::class;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $directories = ['core' => $this->root . '/core'];
      $directories += array_map(function (Extension $extension) {
        return $this->root . '/' . $extension->getPath();
      }, $this->moduleHandler->getModuleList());
      $this->discovery = new YamlDiscovery('link_relation', $directories);
    }
    return $this->discovery;
  }

}

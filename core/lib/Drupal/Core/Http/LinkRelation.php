<?php

namespace Drupal\Core\Http;

use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a single link relationship.
 *
 * @todo pointing to actual documentation.
 */
class LinkRelation extends PluginBase implements LinkRelationInterface {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationshipUrl() {
    return isset($this->pluginDefinition['relationship']) ? $this->pluginDefinition['relationship'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
  }

  /**
   * {@inheritdoc}
   */
  public function getReference() {
    return isset($this->pluginDefinition['reference']) ? $this->pluginDefinition['reference'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getNotes() {
    return isset($this->pluginDefinition['notes']) ? $this->pluginDefinition['notes'] : '';
  }

}

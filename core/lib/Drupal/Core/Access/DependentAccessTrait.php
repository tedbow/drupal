<?php

namespace Drupal\Core\Access;

/**
 * Implements \Drupal\Core\Access\DependentAccessInterface.
 */
trait DependentAccessTrait {

  /**
   * The access dependencies.
   *
   * @var \Drupal\Core\Access\AccessibleInterface[]
   */
  protected $accessDependencies = [];

  /**
   * {@inheritdoc}
   */
  public function setAccessDependencies(array $access_dependencies) {
    $this->accessDependencies = $access_dependencies;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessDependencies() {
    return $this->accessDependencies;
  }

}

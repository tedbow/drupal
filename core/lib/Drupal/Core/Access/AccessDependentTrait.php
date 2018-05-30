<?php

namespace Drupal\Core\Access;

/**
 * Implements \Drupal\Core\Access\AccessDependentInterface.
 */
trait AccessDependentTrait {

  /**
   * The access dependency.
   *
   * @var \Drupal\Core\Access\AccessibleInterface
   */
  protected $accessDependency;

  /**
   * {@inheritdoc}
   */
  public function setAccessDependency(AccessibleInterface $access_dependency) {
    $this->accessDependency = $access_dependency;

  }

  /**
   * {@inheritdoc}
   */
  public function getAccessDependency() {
    return $this->accessDependency;
  }

}

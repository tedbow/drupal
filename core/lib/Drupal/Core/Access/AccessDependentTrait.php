<?php

namespace Drupal\Core\Access;

/**
 * Implements \Drupal\Core\Access\AccessDependentInterface.
 */
trait AccessDependentTrait {

  /**
   * The object that are depended on.
   *
   * @var \Drupal\Core\Access\AccessibleInterface
   */
  protected $accessDependee;

  /**
   * {@inheritdoc}
   */
  public function setAccessDependee(AccessibleInterface $access_dependee) {
    $this->accessDependee = $access_dependee;

  }

  /**
   * {@inheritdoc}
   */
  public function getAccessDependee() {
    return $this->accessDependee;
  }

}

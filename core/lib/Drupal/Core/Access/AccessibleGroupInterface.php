<?php

namespace Drupal\Core\Access;

/**
 * Extends AccessibleInterface to allow for access objects that have multiple
 * dependencies.
 */
interface AccessibleGroupInterface extends AccessibleInterface {

  /**
   * @param \Drupal\Core\Access\AccessibleInterface $dependency
   *
   * @return $this
   */
  public function addDependency(AccessibleInterface $dependency);
}

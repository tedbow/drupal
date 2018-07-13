<?php

namespace Drupal\Core\Access;

/**
 * Extends AccessibleInterface to allow for access objects that have multiple
 * dependencies.
 */
interface AccessibleGroupInterface extends AccessibleInterface {

  /**
   * Adds an access dependency.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $dependency
   *   The access dependency.
   *
   * @return $this
   */
  public function addDependency(AccessibleInterface $dependency);

  /**
   * Gets the access dependencies.
   *
   * @return \Drupal\Core\Access\AccessibleInterface[]
   *   The access dependencies.
   */
  public function getDependencies();

}

<?php

namespace Drupal\Core\Access;

/**
 * An interface to allow adding an access dependency.
 */
interface RefinableDependentAccessInterface extends DependentAccessInterface {

  /**
   * Sets the access dependency.
   *
   * If an access dependency is already set this will replace the existing
   * dependency.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependency
   *   The object upon which access depends.
   *
   * @return $this
   */
  public function setAccessDependency(AccessibleInterface $access_dependency);

  /**
   * Adds an access dependency into the existing access dependency.
   *
   * If no existing dependency is currently set this will set the dependency
   * will be set to the new value.
   *
   * If there is an existing dependency and it does not implement
   * AccessibleGroupInterface the dependency will be set as a new AccessGroupAnd
   * instance with the existing and new dependencies as the members of the
   * group.
   *
   * If there is an existing dependency and it does implement
   * AccessibleGroupInterface the dependency well added to the existing access
   * group.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependency
   *   The access dependency to merge.
   *
   * @return $this
   */
  public function addAccessDependency(AccessibleInterface $access_dependency);

}

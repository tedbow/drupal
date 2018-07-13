<?php

namespace Drupal\Core\Access;

/**
 * An interface to allow adding an access dependency.
 */
interface RefinableDependentAccessInterface extends DependentAccessInterface {

  /**
   * Sets the access dependency.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependency
   *   The object upon which access depends.
   *
   * @return $this
   */
  public function setAccessDependency(AccessibleInterface $access_dependency);

}

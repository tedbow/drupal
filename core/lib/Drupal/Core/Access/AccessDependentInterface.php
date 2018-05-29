<?php

namespace Drupal\Core\Access;

/**
 * Interface for objects that are dependent on other accessible objects.
 */
interface AccessDependentInterface {

  /**
   * Sets the access dependee.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependee
   *   The access dependee.
   */
  public function setAccessDependee(AccessibleInterface $access_dependee);

  /**
   * Gets the access dependee.
   *
   * @return \Drupal\Core\Access\AccessibleInterface
   *   The access dependee.
   */
  public function getAccessDependee();

}

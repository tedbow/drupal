<?php

namespace Drupal\Core\Access;

/**
 * Interface for AccessibleInterface objects that have an access dependency.
 *
 * Objects should implement this interface when their access depends on access
 * to another object that implements \Drupal\Core\Access\AccessibleInterface.
 * This interface simply provides the getter and setter methods for the access
 * dependency object. Objects that implement this interface are responsible for
 * checking access of the access dependency because the dependency may not take
 * effect in all cases. For instance an entity may only need the access
 * dependency set when it is embedded within another entity and its access
 * should be dependent on access to the entity in which it is embedded.
 *
 * To check the access to the dependency the object implementing this interface
 * can use code like this:
 * @code
 * $accessible->getAccessDependee()->access($op, $account, TRUE);
 * @endcode
 */
interface AccessDependentInterface extends AccessibleInterface {

  /**
   * Sets the access dependency.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependency
   *   The access dependency.
   */
  public function setAccessDependency(AccessibleInterface $access_dependency);

  /**
   * Gets the access dependency.
   *
   * @return \Drupal\Core\Access\AccessibleInterface|null
   *   The access dependency or NULL if none has been set.
   */
  public function getAccessDependency();

}

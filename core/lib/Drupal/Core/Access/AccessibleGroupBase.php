<?php

namespace Drupal\Core\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * A base class for accessible groups classes.
 */
abstract class AccessibleGroupBase implements AccessibleGroupInterface {

  /**
   * The access dependencies.
   *
   * @var \Drupal\Core\Access\AccessibleInterface[]
   */
  protected $dependencies = [];

  /**
   * {@inheritdoc}
   */
  public function addDependency(AccessibleInterface $dependency) {
    $this->dependencies[] = $dependency;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = NULL;
    foreach ($this->dependencies as $dependency) {
      $dependency_access_result = $dependency->access($operation, $account, TRUE);
      if ($access_result === NULL) {
        $access_result = $dependency_access_result;
      }
      else {
        $access_result = $this->doCombineAccess($access_result, $dependency_access_result);
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * Combines the access result of one dependency to previous dependencies.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $accumulatedAccess
   *   The combine access result of previous dependencies.
   * @param \Drupal\Core\Access\AccessResultInterface $dependencyAccess
   *   The access result of the current dependency.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined access result.
   */
  abstract protected function doCombineAccess(AccessResultInterface $accumulatedAccess, AccessResultInterface $dependencyAccess);

}

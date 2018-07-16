<?php

namespace Drupal\block_content\Access;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * A base class for accessible groups classes.
 *
 * @internal
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
    if (empty($this->dependencies)) {
      return AccessResult::neutral();
    }
    $access_result = $this->dependencies[0]->access($operation, $account, TRUE);
    if (count($this->dependencies) > 1) {
      foreach (array_slice($this->dependencies, 1) as $dependency) {
        $access_result = $this->combineAccess($access_result, $dependency->access($operation, $account, TRUE));
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies() {
    return $this->dependencies;
  }

  /**
   * Combines the access result of one dependency to previous dependencies.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $accumulated_access
   *   The combined access result of previous dependencies.
   * @param \Drupal\Core\Access\AccessResultInterface $dependency_access
   *   The access result of the current dependency.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined access result.
   */
  abstract protected function combineAccess(AccessResultInterface $accumulated_access, AccessResultInterface $dependency_access);

}

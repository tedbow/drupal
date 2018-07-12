<?php

namespace Drupal\Core\Access;

use Drupal\Core\Session\AccountInterface;

abstract class AccessibleGroupBase implements AccessibleGroupInterface{

  /**
   * The access dependencies.
   *
   * @var \Drupal\Core\Access\AccessibleInterface[]
   */
  protected $dependencies =[];

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
    $access = $this->doAccessCheck($operation, $account);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * @param string $operation
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *
   */
  abstract protected function doAccessCheck($operation, AccountInterface $account);

}

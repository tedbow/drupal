<?php

namespace Drupal\Core\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * An access group where all the dependencies must be allowed.
 */
class AccessGroupAnd extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function doAccessCheck($operation, AccountInterface $account) {
    $access = new AccessResultAllowed();
    foreach ($this->dependencies as $dependency) {
      $access = $access->andIf($dependency->access($operation, $account, TRUE));
    }
    return $access;
  }

}

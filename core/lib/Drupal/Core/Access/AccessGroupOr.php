<?php

namespace Drupal\Core\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * An access group where at least one dependencies must be allowed.
 */
class AccessGroupOr extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function doAccessCheck($operation, AccountInterface $account) {
    $access = new AccessResultNeutral();
    foreach ($this->dependencies as $dependency) {
      $access = $access->orIf($dependency->access($operation, $account, TRUE));
    }
    return $access;
  }

}

<?php

namespace Drupal\Core\Access;

/**
 * An access group where all the dependencies must be allowed.
 */
class AccessGroupAnd extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function doCombineAccess(AccessResultInterface $accumulatedAccess, AccessResultInterface $dependencyAccess) {
    return $accumulatedAccess->andIf($dependencyAccess);
  }

}

<?php

namespace Drupal\Core\Access;


/**
 * An access group where at least one dependencies must be allowed.
 */
class AccessGroupOr extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function doCombineAccess(AccessResultInterface $accumulatedAccess, AccessResultInterface $dependencyAccess) {
    return $accumulatedAccess->orIf($dependencyAccess);
  }

}

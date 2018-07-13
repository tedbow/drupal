<?php

namespace Drupal\Core\Access;

/**
 * An access group where all the dependencies must be allowed.
 */
class AccessGroupAnd extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function combineAccess(AccessResultInterface $accumulated_access, AccessResultInterface $dependency_access) {
    return $accumulated_access->andIf($dependency_access);
  }

}

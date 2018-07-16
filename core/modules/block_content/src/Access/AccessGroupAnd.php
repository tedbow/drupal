<?php

namespace Drupal\block_content\Access;

use Drupal\Core\Access\AccessResultInterface;

/**
 * An access group where all the dependencies must be allowed.
 *
 * @internal
 */
class AccessGroupAnd extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function combineAccess(AccessResultInterface $accumulated_access, AccessResultInterface $dependency_access) {
    return $accumulated_access->andIf($dependency_access);
  }

}

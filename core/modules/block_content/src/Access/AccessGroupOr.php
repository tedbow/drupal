<?php

namespace Drupal\block_content\Access;

use Drupal\Core\Access\AccessResultInterface;

/**
 * An access group where at least one dependencies must be allowed.
 */
class AccessGroupOr extends AccessibleGroupBase {

  /**
   * {@inheritdoc}
   */
  protected function combineAccess(AccessResultInterface $accumulated_access, AccessResultInterface $dependency_access) {
    return $accumulated_access->orIf($dependency_access);
  }

}

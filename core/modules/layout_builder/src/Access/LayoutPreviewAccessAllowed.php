<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Accessible class to allow access for inline blocks in the Layout Builder.
 */
class LayoutPreviewAccessAllowed implements AccessibleInterface {

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($operation === 'view' && $return_as_object) {
      return AccessResult::allowed();
    }
    // If expected arguments return forbidden access.
    return AccessResult::forbidden();
  }

}

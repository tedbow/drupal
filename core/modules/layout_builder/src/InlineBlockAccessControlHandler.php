<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Inline block entity.
 *
 * @see \Drupal\layout_builder\Entity\InlineBlock.
 */
class InlineBlockAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\layout_builder\Entity\InlineBlockInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished inline block entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published inline block entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit inline block entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete inline block entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add inline block entities');
  }

}

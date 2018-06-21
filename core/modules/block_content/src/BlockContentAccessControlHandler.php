<?php

namespace Drupal\block_content;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the custom block entity type.
 *
 * @see \Drupal\block_content\Entity\BlockContent
 */
class BlockContentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view') {
      $access = AccessResult::allowedIf($entity->isPublished())->addCacheableDependency($entity)
        ->orIf(AccessResult::allowedIfHasPermission($account, 'administer blocks'));
    }
    else {
      $access = parent::checkAccess($entity, $operation, $account);
    }
    /** @var \Drupal\block_content\BlockContentInterface $entity */
    if ($entity->hasParentEntity()) {
      if ($parent_entity = $entity->getParentEntity()) {
        $access = $access->andIf($parent_entity->access($operation, $account, TRUE))->addCacheableDependency($entity);
      }
      else {
        // The entity has a parent but it was not able to be loaded.
        return AccessResult::forbidden('Parent entity not available.')->addCacheableDependency($entity);
      }
    }
    return $access;
  }

}

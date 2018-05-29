<?php

namespace Drupal\block_content;

use Drupal\Core\Access\AccessDependentInterface;
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
    $dependee_access = NULL;
    /** @var \Drupal\block_content\BlockContentInterface $entity */
    if ($entity->isReusable() === FALSE) {
      if (!$entity instanceof AccessDependentInterface) {
        throw new \Exception("Non-reusable block entities must implement \Drupal\Core\Access\AccessDependentInterface for access control.");
      }
      $dependee = $entity->getAccessDependee();
      if (empty($dependee)) {
        return AccessResult::forbidden("Non-reusable blocks must set an access dependee for access control.")->addCacheableDependency($dependee);
      }
      $dependee_access = $dependee->access($operation, $account, TRUE);
    }
    if ($operation === 'view') {
      $access = AccessResult::allowedIf($entity->isPublished())->addCacheableDependency($entity)
        ->orIf(AccessResult::allowedIfHasPermission($account, 'administer blocks'));

    }
    else {
      $access = parent::checkAccess($entity, $operation, $account);
    }

    if ($dependee_access) {
      $access->andIf($dependee_access);
    }
    return $access;
  }

}

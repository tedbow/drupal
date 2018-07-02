<?php

namespace Drupal\block_content;

use Drupal\Core\Access\AccessDependentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
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
    if ($entity->isReusable() === FALSE) {
      if (!$entity instanceof AccessDependentInterface) {
        throw new \LogicException("Non-reusable block entities must implement \Drupal\Core\Access\AccessDependentInterface for access control.");
      }
      $dependency = $entity->getAccessDependency();
      if (empty($dependency)) {
        return AccessResult::forbidden("Non-reusable blocks must set an access dependency for access control.");
      }
      if (empty($dependency->in_preview)) {
        $access = $access->andIf($dependency->access($operation, $account, TRUE));
      }
    }
    return $access;
  }

}

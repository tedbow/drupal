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
    if ($operation === 'view') {
      $dependee_access = NULL;
      /** @var \Drupal\block_content\BlockContentInterface $entity */
      if (!$entity->isReusable()) {
        if (!$entity instanceof AccessDependentInterface) {
          throw new \Exception("what?");
        }
        $dependee = $entity->getAccessDependee();
        if (empty($dependee)) {
          return AccessResult::forbidden("No dependee entity set.")->addCacheableDependency($dependee);
        }
        $dependee_access = $dependee->access($operation, $account, TRUE);
      }
      $access = AccessResult::allowedIf($entity->isPublished())->addCacheableDependency($entity)
        ->orIf(AccessResult::allowedIfHasPermission($account, 'administer blocks'));
      if ($dependee_access) {
        $access->andIf($dependee_access);
      }
      return $access;

    }
    return parent::checkAccess($entity, $operation, $account);
  }

}

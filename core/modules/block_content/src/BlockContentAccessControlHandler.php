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
      /** @var \Drupal\block_content\BlockContentInterface $entity */
      if (!$entity->isReusable()) {
        if (!$entity instanceof AccessDependentInterface) {
          throw new \Exception("what?");
        }
        if (empty($entity->getAccessDependees())) {
          return AccessResult::forbidden("None set");
        }
        if (!$entity->dependeeAccess($operation, $account)) {
          return AccessResult::forbidden('no access to parent');
        }
      }
      return AccessResult::allowedIf($entity->isPublished())->addCacheableDependency($entity)
        ->orIf(AccessResult::allowedIfHasPermission($account, 'administer blocks'));
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}

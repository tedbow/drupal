<?php

namespace Drupal\block_content;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the custom block entity type.
 *
 * @see \Drupal\block_content\Entity\BlockContent
 */
class BlockContentAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a BlockContentAccessControlHandler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database) {
    parent::__construct($entity_type);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database')
    );
  }

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
      if ($entity->get('parent_status')->value === BlockContentInterface::PARENT_DELETED) {
        // If the blocks parent has been deleted then access is forbidden. This
        // must be checked before loading the parent entity because if the
        // parent entity type allows arbitrary IDs then the entity type ID could
        // be reused after the original parent entity was deleted.
        $access = $access->andIf(AccessResult::forbidden('The parent entity has been deleted.'));
      }
      else {
        if ($parent_entity = $entity->getParentEntity()) {
          $access = $access->andIf($parent_entity->access($operation, $account, TRUE));
        }
        else {
          // The entity has a parent but it was not able to be loaded.
          $access = $access->andIf(AccessResult::forbidden('Parent entity not available.'));
        }
      }
    }
    return $access;
  }

}

<?php

namespace Drupal\block_content;

use Drupal\Core\Access\AccessDependentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\InlineBlockContentUsage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the custom block entity type.
 *
 * @see \Drupal\block_content\Entity\BlockContent
 */
class BlockContentAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * @var \Drupal\layout_builder\InlineBlockContentUsage
   */
  protected $usage;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeInterface $entity_type, InlineBlockContentUsage $usage, Connection $database, EntityTypeManagerInterface $entit_type_manager) {
    parent::__construct($entity_type);
    $this->usage = $usage;
    $this->database = $database;
    $this->entityTypeManager = $entit_type_manager
  }


  /**
   * Instantiates a new instance of this entity handler.
   *
   * This is a factory method that returns a new instance of this object. The
   * factory should pass any needed dependencies into the constructor of this
   * object, but not the container itself. Every call to this method must return
   * a new instance of this object; that is, it may not implement a singleton.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this object should use.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return static
   *   A new instance of the entity handler.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('inline_block_content.usage'),
      $container->get('database'),
      $container->get('entity_type.manager')
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
    if ($entity->isReusable() === FALSE) {
      if (!$entity instanceof AccessDependentInterface) {
        throw new \LogicException("Non-reusable block entities must implement \Drupal\Core\Access\AccessDependentInterface for access control.");
      }
      $dependency = $entity->getAccessDependency();
      if (empty($dependency)) {
        $dependency = $this->getDepency($entity);
        return AccessResult::forbidden("Non-reusable blocks must set an access dependency for access control.");
      }
      $access->andIf($dependency->access($operation, $account, TRUE));
    }
    return $access;
  }

  protected function getDepency(BlockContentInterface $block_content) {
    if (($layout_entity_info = $this->usage->getUsage($block_content->id()))) {
      $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
      $layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id);
      if ($this->isLayoutCompatibleEntity($layout_entity)) {
        if (!$layout_entity->getEntityType()->isRevisionable()) {
          $block_content->setAccessDependency($layout_entity);
          return;
        }
        else {
          foreach ($this->getRevisionIds($layout_entity) as $revision_id) {
            $revision = $layout_entity_storage->loadRevision($revision_id);
            $block_revision_ids = $this->getInBlockRevisionIdsInSection($this->getEntitySections($revision));
            if (in_array($block_content->getRevisionId(), $block_revision_ids)) {
              $block_content->setAccessDependency($revision);
              return;
            }
          }
        }
      }

    }
  }

}

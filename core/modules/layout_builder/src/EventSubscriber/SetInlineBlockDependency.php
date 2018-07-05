<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\block_content\BlockContentEvents;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\Event\BlockContentGetDependencyEvent;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\InlineBlockContentUsage;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber class that sets the access dependency on inline blocks.
 *
 * When used within the layout builder the access dependency for inline blocks
 * will be explicitly set but if access evaluated outside of the layout builder
 * then the dependency may not have been set.
 *
 * For determining 'view' or 'download' access to a file entity that is attached
 * to a content block via a field that is using the private file system the file
 * access handler will evaluate access on the content block without setting the
 * dependency.
 *
 * @see \Drupal\file\FileAccessControlHandler::checkAccess()
 * @see \Drupal\block_content\BlockContentAccessControlHandler::checkAccess()
 */
class SetInlineBlockDependency implements EventSubscriberInterface, ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The inline block content usage service.
   *
   * @var \Drupal\layout_builder\InlineBlockContentUsage
   */
  protected $usage;

  /**
   * Constructs SetInlineBlockDependency object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\layout_builder\InlineBlockContentUsage $usage
   *   The inline block content usage service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, InlineBlockContentUsage $usage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->usage = $usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('inline_block_content.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      BlockContentEvents::INLINE_BLOCK_GET_DEPENDENCY => 'onGetDependency',
    ];
  }

  /**
   * Handles the BlockContentEvents::INLINE_BLOCK_GET_DEPENDENCY event.
   *
   * @param \Drupal\block_content\Event\BlockContentGetDependencyEvent $event
   *   The event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onGetDependency(BlockContentGetDependencyEvent $event) {
    $this->setLayoutDependency($event->getBlockContent());
  }

  /**
   * Sets the access dependency on a non-reusable block content entity.
   *
   * If the content block is used in a layout for a non-revisionable entity the
   * entity will be set.
   *
   * If the content block is used in a layout for a revisionable entity the
   * first revision that uses the block will be set as the access dependency. A
   * module can override this behavior by creating an event subscriber that sets
   * a different revision as the access dependency.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The block content entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function setLayoutDependency(BlockContentInterface $block_content) {
    if ($block_content->isReusable() || $block_content->getAccessDependency()) {
      // If the block is reusable or if the block already has its dependency set
      // then there is nothing to do here.
      return;
    }

    $layout_entity_info = $this->usage->getUsage($block_content->id());
    if (empty($layout_entity_info)) {
      // If the block does not have usage information then we cannot set a
      // dependency. It may be used by another module besides layout builder.
      return;
    }
    /** @var \Drupal\layout_builder\InlineBlockContentUsage $usage */
    $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
    $layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id);
    if ($this->isLayoutCompatibleEntity($layout_entity)) {
      if (!$layout_entity->getEntityType()->isRevisionable()) {
        $block_content->setAccessDependency($layout_entity);
        return;
      }
      foreach ($this->getEntityRevisionIds($layout_entity) as $revision_id) {
        $revision = $layout_entity_storage->loadRevision($revision_id);
        $block_revision_ids = $this->getInBlockRevisionIdsInSection($this->getEntitySections($revision));
        if (in_array($block_content->getRevisionId(), $block_revision_ids)) {
          $block_content->setAccessDependency($revision);
          return;
        }
      }
    }
  }

  /**
   * Gets the revision IDs for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int[]
   *   The revision IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityRevisionIds(EntityInterface $entity) {
    $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
    if ($revision_table = $entity_type->getRevisionTable()) {
      $query = $this->database->select($revision_table);
      $query->condition($entity_type->getKey('id'), $entity->id());
      $query->fields($revision_table, [$entity_type->getKey('revision')]);
      $query->orderBy($entity_type->getKey('revision'), 'DESC');
      return $query->execute()->fetchCol();
    }
    return [];
  }

}

<?php

namespace Drupal\block_content;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A class for deleting 'block_content' entities when parents are deleted.
 *
 * @internal
 */
class BlockContentWithParentDeleter implements ContainerInjectionInterface {

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

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
   * Constructs a new  BlockContentWithParentDeleter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->storage = $entityTypeManager->getStorage('block_content');
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Handles reacting to a deleting a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handleEntityDelete(EntityInterface $entity) {
    if (!$this->isUsingDataTables($entity->getEntityTypeId())) {
      // If either entity type does not have a data table we need to remove
      // 'parent_entity_id' so that we are able to find the entities to delete
      // in ::getUnused(). We can not delete the actual entities here because
      // it may take too long in a single request. Also the 'block_content'
      // entities may also be parents are other 'block_content' entities.
      // Therefore deleting the block entities here could trigger a cascade
      // delete.
      $block_storage = $this->entityTypeManager->getStorage('block_content');
      $query = $block_storage->getQuery();
      $query->condition('parent_entity_id', $entity->id());
      $query->condition('parent_entity_type', $entity->getEntityTypeId());
      $block_ids = $query->execute();
      foreach ($block_ids as $block_id) {
        /** @var \Drupal\block_content\BlockContentInterface $block */
        $block = $block_storage->load($block_id);
        $block->set('parent_entity_id', NULL);
        $block->save();
      }
    }
  }

  /**
   * Removes unused block content entities.
   *
   * @param int $limit
   *   The maximum number of block content entities to remove.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeUnused($limit = 100) {
    foreach ($this->getUnused($limit) as $block_content_id) {
      if ($block = $this->storage->load($block_content_id)) {
        $block->delete();
      }
    }
  }

  /**
   * Get unused IDs of blocks.
   *
   * @param int $limit
   *   The limit of block IDs to return.
   *
   * @return int[]
   *   The block IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUnused($limit) {
    $block_ids = [];
    foreach ($this->getParentEntityTypeIds() as $entity_type_id) {
      if ($this->isUsingDataTables($entity_type_id)) {
        $new_block_ids = $this->getUnusedBlockIdsForEntityWithDataTable($limit - count($block_ids), $entity_type_id);
      }
      else {
        // For parent entity types that don't use a datatable we removed
        // 'parent_entity_id' on entity delete.
        // @see ::handleEntityDelete()
        $block_query = $this->entityTypeManager->getStorage('block_content')->getQuery();
        $block_query->condition('parent_entity_type', $entity_type_id);
        $block_query->notExists('parent_entity_id');
        $block_query->range(0, $limit - count($block_ids));
        $new_block_ids = $block_query->execute();
      }
      $block_ids = array_merge($block_ids, $new_block_ids);
      if (count($block_ids) >= $limit) {
        break;
      }
    }
    return $block_ids;
  }

  /**
   * Gets the unused block IDs for an entity type using a datatable.
   *
   * @param int $limit
   *   The limit of number of block IDs to retrieve.
   * @param string $parent_entity_type_id
   *   The parent entity type ID.
   *
   * @return int[]
   *   The block IDs.
   */
  protected function getUnusedBlockIdsForEntityWithDataTable($limit, $parent_entity_type_id) {
    $parent_type_definition = $this->entityTypeManager->getDefinition($parent_entity_type_id);
    $block_type_definition = $this->entityTypeManager->getDefinition('block_content');
    $sub_query = $this->database
      ->select($parent_type_definition->getDataTable(), 'parent');
    $parent_id_key = $parent_type_definition->getKey('id');
    $sub_query->fields('parent', [$parent_id_key]);
    $sub_query->where("blocks.parent_entity_id = parent.$parent_id_key");

    $query = Database::getConnection()->select($block_type_definition->getDataTable(), 'blocks');
    $query->fields('blocks', [$block_type_definition->getKey('id')]);
    $query->isNotNull('parent_entity_id');
    $query->condition('parent_entity_type', $parent_type_definition->id());
    $query->notExists($sub_query);
    $query->range(0, $limit);
    return $query->execute()->fetchCol();
  }

  /**
   * Determines if parent entity and 'block_content' type are using datatables.
   *
   * @param string $parent_entity_type_id
   *   The parent entity type.
   *
   * @return bool
   *   TRUE if the parent entity and 'block_content' are using datatables.
   */
  protected function isUsingDataTables($parent_entity_type_id) {
    return $this->entityTypeManager->getDefinition($parent_entity_type_id)->getDataTable() && $this->entityTypeManager->getDefinition('block_content')->getDataTable();
  }

  /**
   * Gets the possible entity type IDs for 'block_content' parents.
   *
   * @return string[]
   *   The possible entity type IDs used for 'block_content' parents. If the
   *   'block_content' entity type is using a datatable we are able to
   *   return only the IDs for types that actually currently being used as
   *   parents, otherwise all entity type IDs are returned.
   */
  protected function getParentEntityTypeIds() {
    if ($datatable = $this->entityTypeManager->getDefinition('block_content')->getDataTable()) {
      // If 'block_content' entities are using a datatable we can only return
      // only entity types are currently used as parents. This will reduce the
      // number of queries needed to find 'block_content' entities with deleted
      // parents.
      return $this->database->select($datatable)
        ->fields($datatable, ['parent_entity_type'])
        ->isNotNull('parent_entity_type')
        ->distinct()
        ->execute()
        ->fetchCol();
    }
    else {
      return array_keys($this->entityTypeManager->getDefinitions());
    }
  }

}

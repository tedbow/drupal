<?php

namespace Drupal\block_content;

use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
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
   * Constructs a new  BlockContentWithParentDeleter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->storage = $entityTypeManager->getStorage('block_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($this->isUsingDataTables($definition->id())) {
        $new_block_ids = $this->getUnusedBlockIdsForEntityWithDataTable($limit - count($block_ids), $definition);
      }
      else {
        // For parent entity types that don't use a datatable we remove
        // 'parent_entity_id' on entity delete.
        // @see ::handleEntityDelete()
        $block_query = $this->entityTypeManager->getStorage('block_content')->getQuery();
        $block_query->condition('parent_entity_type', $definition->id());
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
   * @param \Drupal\Core\Entity\EntityTypeInterface $parent_type_definition
   *   The parent entity type definition.
   *
   * @return int[]
   *   The block IDs.
   */
  protected function getUnusedBlockIdsForEntityWithDataTable($limit, EntityTypeInterface $parent_type_definition) {
    $block_type_definition = $this->entityTypeManager->getDefinition('block_content');
    $sub_query = Database::getConnection()
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

}

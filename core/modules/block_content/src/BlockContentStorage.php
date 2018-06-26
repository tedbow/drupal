<?php

namespace Drupal\block_content;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for content blocks.
 *
 * This extends the base storage class, adding required special handling for
 * 'block_content' entities.
 */
class BlockContentStorage extends SqlContentEntityStorage implements BlockContentStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function setParentStatus($parent_entity_type, $parent_id, $parent_status) {
    $datatable = $this->entityManager->getDefinition('block_content')->getDataTable();
    $this->database->update($datatable)
      ->fields(['parent_status' => $parent_status])
      ->condition('parent_entity_type', $parent_entity_type)
      ->condition('parent_entity_id', $parent_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlocksWithParentDeleted($limit = 100) {
    $query = $this->getQuery();
    $query->condition('parent_status', BlockContentInterface::PARENT_DELETED)
      ->range(0, $limit)->execute();
    $blocks = $this->loadMultiple($query->execute());
    $this->delete($blocks);
  }
}

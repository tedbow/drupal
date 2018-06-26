<?php

namespace Drupal\block_content;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for block_content entity storage classes.
 */
interface BlockContentStorageInterface extends ContentEntityStorageInterface {

  /**
   * Sets the parent status for matching blocks.
   *
   * @param string $parent_entity_type
   *   The parent entity type.
   * @param string $parent_id
   *   The parent entity id.
   * @param int $parent_status
   *   The parent status.
   */
  public function setParentStatus($parent_entity_type, $parent_id, $parent_status);

  /**
   * Delete blocks with a deleted parent entity.
   *
   * @param int $limit
   *   The limit of blocks to delete.
   */
  public function deleteBlocksWithParentDeleted($limit = 100);

}

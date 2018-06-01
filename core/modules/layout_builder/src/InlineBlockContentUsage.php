<?php

namespace Drupal\layout_builder;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Service class to track non-reusable Blocks entities usage.
 */
class InlineBlockContentUsage {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Creates an InlineBlockContentUsage object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Add a usage record.
   *
   * @param \Drupal\block_content\Entity\BlockContent $block
   *   The block content entity to track.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The parent entity.
   *
   * @throws \Exception
   */
  public function addUsage(BlockContent $block, EntityInterface $parent_entity) {
    $this->connection->merge('inline_block_content_usage')
      ->keys([
        'entity_id' => $block->id(),
        'parent_entity_id' => $parent_entity->id(),
        'parent_entity_type' => $parent_entity->getEntityTypeId(),
      ])->execute();
  }

  /**
   * Gets unused inline block content IDs.
   *
   * @param int $limit
   *   The maximum number of block content entity IDs to return.
   *
   * @return int[]
   *   The entity IDs.
   */
  public function getUnused($limit = 100) {
    $query = $this->connection->select('inline_block_content_usage', 't');
    $query->fields('t', ['entity_id']);
    $query->isNull('parent_entity_id');
    $query->isNull('parent_entity_type');
    return $query->range(0, $limit)->execute()->fetchCol();
  }

  /**
   * Remove usage record by parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   * @param bool $delete_record
   *   Whether to deleted the usage record.
   */
  public function removeByParent(EntityInterface $entity, $delete_record = FALSE) {
    if ($delete_record) {
      $query = $this->connection->delete('inline_block_content_usage');
    }
    else {
      $query = $this->connection->update('inline_block_content_usage');
      $query->fields([
        'parent_entity_type' => NULL,
        'parent_entity_id' => NULL,
      ]);
    }
    $query->condition('parent_entity_type', $entity->getEntityTypeId());
    $query->condition('parent_entity_id', $entity->id());
    $query->execute();
  }

  /**
   * Delete the content blocks and delete the usage records.
   *
   * @param int[] $block_content_ids
   *   The block content entity IDs.
   */
  public function deleteUsage(array $block_content_ids) {
    $query = $this->connection->delete('inline_block_content_usage')->condition('entity_id', $block_content_ids, 'IN');
    $query->execute();
  }

}

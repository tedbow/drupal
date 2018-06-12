<?php

namespace Drupal\layout_builder;

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
   * @param int $block_content_id
   *   The block content id.
   * @param string $parent_entity_type
   *   The parent entity type.
   * @param string $parent_entity_id
   *   The parent entity id.
   *
   * @throws \Exception
   */
  public function addUsage($block_content_id, $parent_entity_type, $parent_entity_id) {
    $this->connection->merge('inline_block_content_usage')
      ->keys([
        'block_content_id' => $block_content_id,
        'parent_entity_id' => $parent_entity_id,
        'parent_entity_type' => $parent_entity_type,
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
    $query->fields('t', ['block_content_id']);
    $query->isNull('parent_entity_id');
    $query->isNull('parent_entity_type');
    return $query->range(0, $limit)->execute()->fetchCol();
  }

  /**
   * Remove usage record by parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   */
  public function removeByParent(EntityInterface $entity) {
    $query = $this->connection->update('inline_block_content_usage')
      ->fields([
        'parent_entity_type' => NULL,
        'parent_entity_id' => NULL,
      ]);
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
    $query = $this->connection->delete('inline_block_content_usage')->condition('block_content_id', $block_content_ids, 'IN');
    $query->execute();
  }

}

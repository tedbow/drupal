<?php

namespace Drupal\layout_builder;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Entity usage service using the database backend.
 */
class DatabaseBackendEntityUsage extends EntityUsageBase {

  /**
   * The name of the SQL table used to store file usage information.
   *
   * @var string
   */
  protected $tableName;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Construct the DatabaseFileUsageBackend.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection which will be used to store the entity usage
   *   information.
   * @param string $table
   *   (optional) The table to store usage info. Defaults to 'entity_usage'.
   */
  public function __construct(Connection $connection, $table = 'entity_usage') {
    $this->connection = $connection;
    $this->tableName = $table;
  }

  /**
   * {@inheritdoc}
   */
  public function add($child_entity_type_id, $child_entity_id, $parent_type, $parent_id, $count = 1) {
    $this->connection->merge($this->tableName)
      ->keys([
        'entity_type' => $child_entity_type_id,
        'entity_id' => $child_entity_id,
        'parent_type' => $parent_type,
        'parent_id' => $parent_id,
      ])
      ->fields(['count' => $count])
      ->expression('count', 'count + :count', [':count' => $count])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function remove($child_entity_type_id, $child_entity_id, $parent_type, $parent_id, $count = 1) {
    // Delete rows that have uses.
    $query = $this->connection->update($this->tableName)
      ->condition('entity_type', $child_entity_type_id)
      ->condition('entity_id', $child_entity_id)
      ->condition('parent_type', $parent_type)
      ->condition('parent_id', $parent_id);
    $query->condition('count', $count, '>=');
    $query->expression('count', 'count - :count', [':count' => $count]);
    if ($result = $query->execute()) {
      /** @var \Drupal\Core\Database\Query\SelectInterface $query */
      $query = $this->connection->select($this->tableName, 't')
        ->condition('entity_type', $child_entity_type_id)
        ->condition('entity_id', $child_entity_id)
        ->condition('parent_type', $parent_type)
        ->condition('parent_id', $parent_id);
      return $query->fields('t', ['count'])->execute()->fetchField();
    }
    else {
      // If not rows were found where the count is greater than or equal $count
      // then set any rows less then to 0.
      $query = $this->connection->update($this->tableName)
        ->condition('entity_type', $child_entity_type_id)
        ->condition('entity_id', $child_entity_id)
        ->condition('parent_type', $parent_type)
        ->condition('parent_id', $parent_id);
      $query->condition('count', $count, '<');
      $query->fields(['count' => 0]);
      $query->execute();
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeByParentEntity($child_entity_type_id, EntityInterface $parent_entity, $retain_usage_record = TRUE) {
    if ($retain_usage_record) {
      $this->connection->update($this->tableName)
        ->condition('entity_type', $child_entity_type_id)
        ->condition('parent_type', $parent_entity->getEntityTypeId())
        ->condition('parent_id', $parent_entity->id())
        ->fields(['count' => 0])
        ->execute();
    }
    else {
      $this->connection->delete($this->tableName)
        ->condition('entity_type', $child_entity_type_id)
        ->condition('parent_type', $parent_entity->getEntityTypeId())
        ->condition('parent_id', $parent_entity->id())
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listUsage(EntityInterface $child_entity) {
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->connection->select($this->tableName, 'u')
      ->condition('entity_type', $child_entity->getEntityTypeId())
      ->condition('entity_id', (string) $child_entity->id());
    $query->fields('u');
    $result = $query->execute();
    $usages = [];
    foreach ($result as $usage) {
      $usages[$usage->parent_type][$usage->parent_id] = $usage->count;
    }
    return $usages;

  }

  /**
   * {@inheritdoc}
   */
  public function getEntitiesWithNoUses($child_entity_type_id, $limit = 100) {
    // @todo Implement $limit logic.
    $query = $this->connection->select($this->tableName);
    $query->fields($this->tableName, ['entity_id']);
    $query->condition('entity_type', $child_entity_type_id)
      ->condition('count', 0);
    $entity_ids = $query->execute()->fetchCol();

    if ($entity_ids) {
      // @todo Use ::notExists() to do this sub query.
      $sub_query = $this->connection->select($this->tableName);
      $sub_query->condition('entity_type', $child_entity_type_id)
        ->condition('count', 0, '>')
        ->condition('entity_id', $entity_ids, 'IN');
      $sub_query->fields($this->tableName, ['entity_id']);
      $used_entity_ids = $sub_query->execute()->fetchCol();
      $unused_entity_ids = array_diff($entity_ids, $used_entity_ids);
      return array_values($unused_entity_ids);
    }
    return [];

  }

  /**
   * {@inheritdoc}
   */
  public function delete($child_entity_type_id, $child_entity_id) {
    $this->connection->delete($this->tableName)
      ->condition('entity_type', $child_entity_type_id)
      ->condition('entity_id', $child_entity_id)
      ->execute();
  }

}

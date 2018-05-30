<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Database\Connection;

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
    // Update rows that have uses.
    $query = $this->connection->update($this->tableName)
      ->condition('entity_type', $child_entity_type_id)
      ->condition('entity_id', $child_entity_id)
      ->condition('parent_type', $parent_type)
      ->condition('parent_id', $parent_id);
    $query->condition('count', $count, '>=');
    $query->expression('count', 'count - :count', [':count' => $count]);
    $result = $query->execute();
    if (empty($result)) {
      // If no rows were found where the count is greater than or equal $count
      // then set any rows less then $count to 0.
      $query = $this->connection->update($this->tableName)
        ->condition('entity_type', $child_entity_type_id)
        ->condition('entity_id', $child_entity_id)
        ->condition('parent_type', $parent_type)
        ->condition('parent_id', $parent_id);
      $query->condition('count', $count, '<');
      $query->fields(['count' => 0]);
      $query->execute();
    }
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->connection->select($this->tableName, 't')
      ->condition('entity_type', $child_entity_type_id)
      ->condition('entity_id', $child_entity_id);
    $query->addExpression('sum(t.count)', 'c');
    return $query->execute()->fetchField();
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
    $query = $this->connection->select($this->tableName, 't1');
    $query->fields('t1', ['entity_id']);
    $query->condition('t1.entity_type', $child_entity_type_id)
      ->condition('count', 0);
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $sub_query = $this->connection->select($this->tableName, 't2');
    $sub_query->where('t1.entity_id = t2.entity_id');
    $sub_query->condition('t2.entity_type', $child_entity_type_id)
      ->condition('count', 0, '>');
    $sub_query->fields('t2', ['entity_id']);
    $query->notExists($sub_query);
    return $query->execute()->fetchCol();

  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple($child_entity_type_id, array $child_entity_ids) {
    $this->connection->delete($this->tableName)
      ->condition('entity_type', $child_entity_type_id)
      ->condition('entity_id', $child_entity_ids, 'IN')
      ->execute();
  }

}

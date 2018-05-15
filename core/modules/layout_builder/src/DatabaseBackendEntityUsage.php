<?php


namespace Drupal\layout_builder;


use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

class DatabaseBackendEntityUsage implements EntityUsageInterface {


  /**
   * The name of the SQL table used to store file usage information.
   *
   * @var string
   */
  protected $tableName;

  /**
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
   *   (optional) The table to store entitys usage info. Defaults to 'v'.
   */
  public function __construct(Connection $connection, $table = 'entity_usage') {
    $this->connection = $connection;

    $this->tableName = $table;
  }
  /**
   * Adds usage by entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $used_entity
   * @param \Drupal\Core\Entity\EntityInterface $user_entity
   * @param int $count
   */
  public function addByEntities(EntityInterface $used_entity, EntityInterface $user_entity, $count = 1) {
    $this->add($used_entity->getEntityTypeId(), $used_entity->id(), $user_entity->getEntityTypeId(), $user_entity->id());
  }

  /**
   * Adds usage by ids.
   *
   * @param $used_entity_type_id
   * @param $used_entity_id
   * @param $user_entity_type_id
   * @param $user_entity_id
   * @param int $count
   */
  public function add($used_entity_type_id, $used_entity_id, $user_entity_type_id, $user_entity_id, $count = 1) {
    $this->connection->merge($this->tableName)
      ->keys([
        'entity_type' => $used_entity_type_id,
        'entity_id' => $used_entity_id,
        'type' => $user_entity_type_id,
        'id' => $user_entity_id,
      ])
      ->fields(['count' => $count])
      ->expression('count', 'count + :count', [':count' => $count])
      ->execute();
  }

  /**
   * Removes usage by entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $used_entity
   * @param \Drupal\Core\Entity\EntityInterface $user_entity
   * @param int $count
   *
   * @return int
   *   The new total uses for the entity.
   */
  public function removeByEntities(EntityInterface $used_entity, EntityInterface $user_entity, $count = 1) {
    $this->remove($used_entity->getEntityTypeId(), $used_entity->id(), $user_entity->getEntityTypeId(), $user_entity->id());
  }

  /**
   * Removes usage by ids.
   *
   * @param $used_entity_type_id
   * @param $used_entity_id
   * @param $user_entity_type_id
   * @param $user_entity_id
   * @param int $count
   *
   * @return int
   *   The new total uses for the entity.
   */
  public function remove($used_entity_type_id, $used_entity_id, $user_entity_type_id = NULL, $user_entity_id = NULL, $count = 1) {
    // Delete rows that have a exact or less value to prevent empty rows.
    $query = $this->connection->update($this->tableName)
      ->condition('entity_type', $used_entity_type_id)
      ->condition('entity_id', $used_entity_id);
    if ($user_entity_type_id && $user_entity_id) {
      $query->condition('type', $user_entity_type_id)
        ->condition('id', $user_entity_id);
    }
    $query->condition('count', 0, '>');
    $query->expression('count', 'count - :count', [':count' => $count]);
    $result = $query->execute();
  }

  /**
   * [@inheritdoc}
   */
  public function removeByUser($used_entity_type_id, EntityInterface $entity, $retain_usage_record = TRUE) {
    if ($retain_usage_record) {
      $this->connection->update($this->tableName)
        ->condition('entity_type', $used_entity_type_id)
        ->condition('type', $entity->getEntityTypeId())
        ->condition('id', $entity->id())
        ->fields(['count' => 0])
        ->execute();
    }
    else {
      $this->connection->delete($this->tableName)
        ->condition('entity_type', $used_entity_type_id)
        ->condition('type', $entity->getEntityTypeId())
        ->condition('id', $entity->id())
        ->execute();
    }
  }

  /**
   * Determines where a entity is used.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file entity.
   *
   * @return array
   *   TBD
   */
  public function listUsage(EntityInterface $file) {
    // TODO: Implement listUsage() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getEntitiesWithNoUses($entity_type_id, $limit = 100) {
    // @todo Implement $limit logic.
    $query = $this->connection->select($this->tableName);
    $query->fields($this->tableName, ['entity_id']);
    $query->condition('entity_type', $entity_type_id)
      ->condition('count', 0);
    $entity_ids = $query->execute()->fetchCol();

    // @todo Use ::notExists() to do this subquery.
    $sub_query = $this->connection->select($this->tableName);
    $sub_query->condition('entity_type', $entity_type_id)
      ->condition('count', 0, '>')
      ->condition('entity_id', $entity_ids, 'IN');
    $sub_query->fields($this->tableName, ['entity_id']);
    $used_entity_ids = $sub_query->execute()->fetchCol();
    return array_diff($entity_ids, $used_entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($entity_type_id, $entity_id) {
    $this->connection->delete($this->tableName)
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', $entity_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByEntity(EntityInterface $entity) {
    $this->delete($entity->getEntityTypeId(), $entity->id());
  }

}

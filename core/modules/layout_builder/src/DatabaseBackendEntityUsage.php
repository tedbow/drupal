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
    $this->remove($used_entity->getEntityTypeId(), $used_entity->id(), $user_entity->getEntityTypeId(), $user_entity->id());
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
    // TODO: Implement removeByEntities() method.
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
  public function remove($used_entity_type_id, $used_entity_id, $user_entity_type_id, $user_entity_id, $count = 1) {
    // TODO: Implement remove() method.
  }

  /**
   * Remove all uses by a user entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function removeByUser(EntityInterface $entity) {
    // TODO: Implement removeByUser() method.
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
   * Gets all entities have been tracked but currently have no uses.
   *
   * This can be used by modules to determine which entities should be deleted.
   *
   * @param (optional) string $entity_type_id
   *
   *
   * @return mixed
   *   ?? ids or entities
   */
  public function getEntitiesWithNoUses($entity_type_id = NULL) {
    // TODO: Implement getEntitiesWithNoUses() method.
  }
}

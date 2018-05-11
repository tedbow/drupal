<?php


namespace Drupal\Core\Entity;


interface EntityUsageInterface {


  /**
   * Adds usage by entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $used_entity
   * @param \Drupal\Core\Entity\EntityInterface $user_entity
   * @param int $count
   */
  public function addByEntities(EntityInterface $used_entity, EntityInterface $user_entity, $count = 1);


  /**
   * Adds usage by ids.
   *
   * @param $used_entity_type_id
   * @param $used_entity_id
   * @param $user_entity_type_id
   * @param $user_entity_id
   * @param int $count
   */
  public function add($used_entity_type_id, $used_entity_id, $user_entity_type_id, $user_entity_id, $count = 1);

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
  public function removeByEntity(EntityInterface $used_entity, EntityInterface $user_entity, $count = 1);

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
  public function remove($used_entity_type_id, $used_entity_id, $user_entity_type_id, $user_entity_id, $count = 1);

  /**
   * Determines where a entity is used.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file entity.
   *
   * @return array
   *   TBD
   */
  public function listUsage(EntityInterface $file);

  /**
   * Gets all entities have been tracked but currently have no uses.
   *
   * This can be used by modules to determine which entities should be deleted.
   *
   * @param string $entity_type_id
   *
   * @return mixed
   *   ?? ids or entities
   */
  public function getEntitiesWithNoUses($entity_type_id);

}

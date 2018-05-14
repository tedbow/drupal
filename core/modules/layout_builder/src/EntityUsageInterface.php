<?php


namespace Drupal\layout_builder;


use Drupal\Core\Entity\EntityInterface;

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
  public function removeByEntities(EntityInterface $used_entity, EntityInterface $user_entity, $count = 1);

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
   * Remove all uses by a user entity.
   *
   * @param $used_entity_type_id
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param bool $retain_usage_record
   *
   * @return
   */
  public function removeByUser($used_entity_type_id, EntityInterface $entity, $retain_usage_record = TRUE);

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
   *   The entity type to query.
   * @param int $limit
   *   The maximum number of entities to fetch.
   *
   * @return int[]
   *   The entities.
   */
  public function getEntitiesWithNoUses($entity_type_id, $limit = 100);

}

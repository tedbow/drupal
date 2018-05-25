<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;

/**
 * Entity usage interface.
 *
 * Be default usage records are still kept when they have a count of "0". This
 * allows finding entities that were used but are not longer being used as
 * opposed to entities that never were tracked.
 *
 * Modules using this service are responsible for deleting the entities with "0"
 * used count.
 */
interface EntityUsageInterface {

  /**
   * Adds usage by entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child_entity
   *   The child entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The parent entity.
   * @param int $count
   *   The count to add.
   */
  public function addByEntities(EntityInterface $child_entity, EntityInterface $parent_entity, $count = 1);

  /**
   * Adds usage by IDs.
   *
   * @param string $child_entity_type_id
   *   The child entity type ID.
   * @param string $child_entity_id
   *   The child entity ID.
   * @param string $parent_type
   *   The parent type.
   * @param string $parent_id
   *   The parent ID.
   * @param int $count
   *   The count to add.
   */
  public function add($child_entity_type_id, $child_entity_id, $parent_type, $parent_id, $count = 1);

  /**
   * Removes usage by entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child_entity
   *   The child entity.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The parent entity.
   * @param int $count
   *   The count to remove.
   *
   * @return int
   *   The new total uses for the entity.
   */
  public function removeByEntities(EntityInterface $child_entity, EntityInterface $parent_entity, $count = 1);

  /**
   * Removes usage by IDs.
   *
   * @param string $child_entity_type_id
   *   The child entity type ID.
   * @param string $child_entity_id
   *   The child entity ID.
   * @param string $parent_type
   *   The parent type.
   * @param string $parent_id
   *   The parent ID.
   * @param int $count
   *   The count to remove.
   *
   * @return int
   *   The new total uses for the entity.
   */
  public function remove($child_entity_type_id, $child_entity_id, $parent_type, $parent_id, $count = 1);

  /**
   * Remove all uses by a parent entity.
   *
   * @param string $child_entity_type_id
   *   The child entity type ID.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The parent entity.
   * @param bool $retain_usage_record
   *   Whether to retain the usage record with count set zero.
   */
  public function removeByParentEntity($child_entity_type_id, EntityInterface $parent_entity, $retain_usage_record = TRUE);

  /**
   * Determines where a entity is used.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child_entity
   *   A child  entity.
   *
   * @return array
   *   A nested array with usage data. The first level is keyed by parent type,
   *   the second by parent ID. The value of
   *   the second level contains the usage count.
   */
  public function listUsage(EntityInterface $child_entity);

  /**
   * Gets all entities have been tracked but currently have no uses.
   *
   * This can be used by modules to determine which entities should be deleted.
   *
   * @param string $child_entity_type_id
   *   The entity type to query.
   * @param int $limit
   *   The maximum number of entities to fetch.
   *
   * @return int[]
   *   The entity IDs.
   */
  public function getEntitiesWithNoUses($child_entity_type_id, $limit = 100);

  /**
   * Delete all usage for an entity.
   *
   * @param string $child_entity_type_id
   *   The entity type ID.
   * @param string $child_entity_id
   *   The entity ID.
   */
  public function delete($child_entity_type_id, $child_entity_id);

  /**
   * Delete all usage records by entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child_entity
   *   The entity.
   */
  public function deleteByChildEntity(EntityInterface $child_entity);

}

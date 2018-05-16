<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;

/**
 * Base class for entity usage service.
 */
abstract class EntityUsageBase implements EntityUsageInterface {

  /**
   * {@inheritdoc}
   */
  public function addByEntities(EntityInterface $child_entity, EntityInterface $parent_entity, $count = 1) {
    $this->add($child_entity->getEntityTypeId(), $child_entity->id(), $parent_entity->getEntityTypeId(), $parent_entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByChildEntity(EntityInterface $child_entity) {
    $this->delete($child_entity->getEntityTypeId(), $child_entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function removeByEntities(EntityInterface $child_entity, EntityInterface $parent_entity, $count = 1) {
    return $this->remove($child_entity->getEntityTypeId(), $child_entity->id(), $parent_entity->getEntityTypeId(), $parent_entity->id());
  }

}

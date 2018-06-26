<?php

namespace Drupal\block_content;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A class for deleting 'block_content' entities when parents are deleted.
 *
 * @internal
 */
class BlockContentWithParentDeleter implements ContainerInjectionInterface {

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new  BlockContentWithParentDeleter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $database) {
    $this->storage = $entityTypeManager->getStorage('block_content');
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Handles reacting to a deleting a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @throws \Exception
   */
  public function handleEntityDelete(EntityInterface $entity) {
    $query = $this->storage->getQuery();
    $query->condition('parent_entity_id', $entity->id());
    $query->condition('parent_entity_type', $entity->getEntityTypeId());
    $block_ids = $query->execute();

    $query = $this->database->insert('block_content_delete')
      ->fields(['block_content_id']);
    foreach ($block_ids as $block_id) {
      $query->values([$block_id]);
    }
    $query->execute();
  }

  /**
   * Removes unused block content entities.
   *
   * @param int $limit
   *   The maximum number of block content entities to remove.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeUnused($limit = 100) {
    $query = $this->database->select('block_content_delete')
      ->range(0, $limit)
      ->fields('block_content_delete', ['block_content_id']);
    foreach ($query->execute()->fetchCol() as $block_content_id) {
      if ($block = $this->storage->load($block_content_id)) {
        $block->delete();
      }
    }
  }

}

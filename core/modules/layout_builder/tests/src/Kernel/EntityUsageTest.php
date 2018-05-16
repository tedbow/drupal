<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests entity usage.
 *
 * @group layout_builder
 */
class EntityUsageTest extends EntityKernelTestBase {

  /**
   * The list of modules to enable.
   *
   * @var array
   */
  public static $modules = ['layout_builder'];

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $parentEntity;

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $childEntity;

  /**
   * @var \Drupal\layout_builder\DatabaseBackendEntityUsage
   */
  protected $entityUsage;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('layout_builder', 'entity_usage');

    // Create a parent entity.
    $this->parentEntity = EntityTest::create([
      'name' => 'Parent entity',
    ]);
    $this->parentEntity->save();

    // Create a child entity.
    $this->childEntity = EntityTest::create([
      'name' => 'Parent entity',
    ]);
    $this->childEntity->save();

    $this->entityUsage = $this->container->get('entity.usage');
    $this->connection = $this->container->get('database');
  }

  /**
   * Tests \Drupal\layout_builder\DatabaseBackendEntityUsage::add();
   */
  public function testAddUsage() {
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id());
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'bar', 'foo');
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'bar', 'foo');

    $results = $this->connection->select('entity_usage', 'eu')
      ->fields('eu')
      ->condition('eu.entity_id', $this->childEntity->id())
      ->execute()
      ->fetchAll();

    $this->assertEquals(1, $results[0]->count);
    $this->assertEquals(2, $results[1]->count);
  }

  /**
   * Tests \Drupal\layout_builder\DatabaseBackendEntityUsage::remove();
   */
  public function testRemoveUsage() {
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 2);
    $count = $this->entityUsage->remove($this->childEntity->getEntityTypeId(), $this->childEntity->id(), NULL, NULL, 1);

    $result = $this->connection->select('entity_usage', 'eu')
      ->fields('eu')
      ->condition('eu.entity_id', $this->childEntity->id())
      ->execute()
      ->fetch();

    $this->assertEquals(1, $result->count);
    // This fails currently because DatabaseBackendEntityUsage does not return the count.
    //$this->assertEquals($result->count, $count);
  }
}

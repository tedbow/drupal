<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests entity usage.
 *
 * @coversDefaultClass \Drupal\layout_builder\DatabaseBackendEntityUsage
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
   * The parent entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $parentEntity;

  /**
   * The child entity 1.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $childEntity;

  /**
   * The child entity 2.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $childEntity2;
  /**
   * The entity usage service.
   *
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
      'name' => 'Child entity 1',
    ]);
    $this->childEntity->save();

    // Create a child entity.
    $this->childEntity2 = EntityTest::create([
      'name' => 'Child entity 2',
    ]);
    $this->childEntity2->save();


    $this->entityUsage = $this->container->get('entity.usage');
    $this->connection = $this->container->get('database');
  }

  /**
   * @covers ::add
   */
  public function testAddUsage() {
    $this->addInitialUses();

    $expected_uses = [
      $this->parentEntity->getEntityTypeId() => [
        $this->parentEntity->id() => 3,
      ],
      'A_PARENT_TYPE' => [
        'A_PARENT_ID' => 2,
      ],
    ];
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));

    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'A_PARENT_TYPE', 'A_PARENT_ID', 2);

    $expected_uses['A_PARENT_TYPE']['A_PARENT_ID'] = 4;
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));
  }

  /**
   * @covers ::remove
   */
  public function testRemoveUsage() {
    $this->addInitialUses();
    $this->assertEquals(4, $this->entityUsage->remove($this->childEntity->getEntityTypeId(), $this->childEntity->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 1));

    $expected_uses = [
      $this->parentEntity->getEntityTypeId() => [
        $this->parentEntity->id() => 2,
      ],
      'A_PARENT_TYPE' => [
        'A_PARENT_ID' => 2,
      ],
    ];
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));

    // Confirm the usage count is never less than 0.
    $this->assertEquals(2, $this->entityUsage->remove($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'A_PARENT_TYPE', 'A_PARENT_ID', 314));
    $expected_uses['A_PARENT_TYPE']['A_PARENT_ID'] = 0;
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));

    $this->assertEquals(0, $this->entityUsage->remove($this->childEntity->getEntityTypeId(), $this->childEntity->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 2));
    $expected_uses[$this->parentEntity->getEntityTypeId()][$this->parentEntity->id()] = 0;
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));
  }

  /**
   * @covers ::getEntitiesWithNoUses
   */
  public function testGetEntitiesWithNoUses() {
    $this->addInitialUses();
    $this->entityUsage->add($this->childEntity2->getEntityTypeId(), $this->childEntity2->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 2);

    $this->assertEmpty($this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->assertEquals(1, $this->entityUsage->remove($this->childEntity2->getEntityTypeId(), $this->childEntity2->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 1));
    $this->assertEmpty($this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->assertEquals(0, $this->entityUsage->remove($this->childEntity2->getEntityTypeId(), $this->childEntity2->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 1));
    $this->assertEquals([$this->childEntity2->id()], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->entityUsage->remove($this->childEntity->getEntityTypeId(), $this->childEntity->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 3);
    $this->assertEquals([$this->childEntity2->id()], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->assertEquals(0, $this->entityUsage->remove($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'A_PARENT_TYPE', 'A_PARENT_ID', 2));
    $this->assertUnsortedArrayEquals([$this->childEntity2->id(), $this->childEntity->id()], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->entityUsage->delete($this->childEntity->getEntityTypeId(), $this->childEntity->id());
    $this->assertEquals([$this->childEntity2->id()], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->entityUsage->delete($this->childEntity2->getEntityTypeId(), $this->childEntity2->id());
    $this->assertEmpty($this->entityUsage->getEntitiesWithNoUses('entity_test'));
  }

  /**
   * Adds initial usage.
   */
  protected function addInitialUses() {
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), $this->parentEntity->getEntityTypeId(), $this->parentEntity->id(), 3);
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'A_PARENT_TYPE', 'A_PARENT_ID');
    $this->entityUsage->add($this->childEntity->getEntityTypeId(), $this->childEntity->id(), 'A_PARENT_TYPE', 'A_PARENT_ID');
  }

  /**
   * Assert that 2 arrays are equal except for sorting.
   *
   * @param array $array_1
   *   The first array.
   * @param array $array_2
   *   The second array.
   */
  protected function assertUnsortedArrayEquals(array $array_1, array $array_2) {
    $this->assertEquals(ksort($array_1), ksort($array_2));
  }

}

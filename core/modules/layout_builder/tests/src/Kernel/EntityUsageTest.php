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
   * {@inheritdoc}
   */
  public static $modules = ['layout_builder'];

  /**
   * The child entity 1.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $childEntity;

  /**
   * The entity usage service.
   *
   * @var \Drupal\layout_builder\DatabaseBackendEntityUsage
   */
  protected $entityUsage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  protected $parentEntityId;

  protected $childEntityId;

  protected $childEntity2Id;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('layout_builder', 'entity_usage');

    // Create a parent entity.
    $this->parentEntityId = 1;

    // Create a child entity.
    $this->childEntityId = 2;

    $childEntity = $this->prophesize(EntityTest::class);
    $childEntity->id()->willReturn($this->childEntityId);
    $childEntity->getEntityTypeId()->willReturn('entity_test');
    $this->childEntity = $childEntity->reveal();

    // Create a child entity.
    $this->childEntity2Id = 3;

    $this->entityUsage = $this->container->get('entity.usage');
    $this->connection = $this->container->get('database');
  }

  /**
   * @covers ::add
   */
  public function testAddUsage() {
    $this->addInitialUses();

    $expected_uses = [
      'entity_test' => [
        $this->parentEntityId => 3,
      ],
      'A_PARENT_TYPE' => [
        'A_PARENT_ID' => 2,
      ],
    ];
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));

    $this->entityUsage->add('entity_test', $this->childEntityId, 'A_PARENT_TYPE', 'A_PARENT_ID', 2);

    $expected_uses['A_PARENT_TYPE']['A_PARENT_ID'] = 4;
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));
  }

  /**
   * @covers ::remove
   */
  public function testRemoveUsage() {
    $this->addInitialUses();
    $this->assertEquals(4, $this->entityUsage->remove('entity_test', $this->childEntityId, 'entity_test', $this->parentEntityId, 1));

    $expected_uses = [
      'entity_test' => [
        $this->parentEntityId => 2,
      ],
      'A_PARENT_TYPE' => [
        'A_PARENT_ID' => 2,
      ],
    ];
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));

    // Confirm the usage count is never less than 0.
    $this->assertEquals(2, $this->entityUsage->remove('entity_test', $this->childEntityId, 'A_PARENT_TYPE', 'A_PARENT_ID', 314));
    $expected_uses['A_PARENT_TYPE']['A_PARENT_ID'] = 0;
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));

    $this->assertEquals(0, $this->entityUsage->remove('entity_test', $this->childEntityId, 'entity_test', $this->parentEntityId, 2));
    $expected_uses['entity_test'][$this->parentEntityId] = 0;
    $this->assertEquals($expected_uses, $this->entityUsage->listUsage($this->childEntity));
  }

  /**
   * @covers ::getEntitiesWithNoUses
   */
  public function testGetEntitiesWithNoUses() {
    $this->addInitialUses();
    $this->entityUsage->add('entity_test', $this->childEntity2Id, 'entity_test', $this->parentEntityId, 2);

    $this->assertEmpty($this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->assertEquals(1, $this->entityUsage->remove('entity_test', $this->childEntity2Id, 'entity_test', $this->parentEntityId, 1));
    $this->assertEmpty($this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->assertEquals(0, $this->entityUsage->remove('entity_test', $this->childEntity2Id, 'entity_test', $this->parentEntityId, 1));
    $this->assertEquals([$this->childEntity2Id], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->entityUsage->remove('entity_test', $this->childEntityId, 'entity_test', $this->parentEntityId, 3);
    $this->assertEquals([$this->childEntity2Id], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->assertEquals(0, $this->entityUsage->remove('entity_test', $this->childEntityId, 'A_PARENT_TYPE', 'A_PARENT_ID', 2));
    $this->assertUnsortedArrayEquals([$this->childEntity2Id, $this->childEntityId], $this->entityUsage->getEntitiesWithNoUses('entity_test'));
    $this->assertUnsortedArrayEquals([$this->childEntity2Id, $this->childEntityId], $this->entityUsage->getEntitiesWithNoUses('entity_test', 2));
    $this->assertCount(1, $this->entityUsage->getEntitiesWithNoUses('entity_test', 1));

    $this->entityUsage->deleteMultiple('entity_test', [$this->childEntityId]);
    $this->assertEquals([$this->childEntity2Id], $this->entityUsage->getEntitiesWithNoUses('entity_test'));

    $this->entityUsage->deleteMultiple('entity_test', [$this->childEntity2Id]);
    $this->assertEmpty($this->entityUsage->getEntitiesWithNoUses('entity_test'));
  }

  /**
   * Adds initial usage.
   */
  protected function addInitialUses() {
    $this->entityUsage->add('entity_test', $this->childEntityId, 'entity_test', $this->parentEntityId, 3);
    $this->entityUsage->add('entity_test', $this->childEntityId, 'A_PARENT_TYPE', 'A_PARENT_ID');
    $this->entityUsage->add('entity_test', $this->childEntityId, 'A_PARENT_TYPE', 'A_PARENT_ID');
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

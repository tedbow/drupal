<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\layout_builder\LayoutEntityHelperTrait
 *
 * @group layout_builder
 */
class LayoutEntityHelperTraitTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'entity_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', ['key_value_expire']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }
  public function providerTestGetSectionStorageForEntity() {
    $data = [];
    $data[] = [
      'entity_view_display',
      [
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
        'status' => TRUE,
        'third_party_settings' => [
          'layout_builder' => [
            'enabled' => TRUE,
          ],
        ],
      ],
      ['display'],
    ];
    $data[] = [
      'entity_test',
      [],
      ['entity', 'display', 'view_mode'],
    ];
    return $data;
  }

  /**
   * @dataProvider providerTestGetSectionStorageForEntity
   */
  public function testGetSectionStorageForEntity($entity_type_id, $values, $expected) {
    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $section_storage_manager->load('')->willReturn(NULL);
    $section_storage_manager->findByContext(Argument::cetera())->will(function ($arguments) {
      return array_keys($arguments[0]);
    });
    $this->container->set('plugin.manager.layout_builder.section_storage', $section_storage_manager->reveal());
    $entity = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->create($values);
    $entity->save();
    $class = new TestLayoutEntityHelperTrait();
    $result = $class->getSectionStorageForEntity($entity);
    $this->assertEquals($expected, $result);
  }

  public function _testOriginalEntityUsesDefaultStorage() {
    $entity = EntityTest::create([]);
    $entity->save();
    $class = new TestLayoutEntityHelperTrait();
    $expected = TRUE;
    $result = $class->originalEntityUsesDefaultStorage($entity);
    $this->assertSame($expected, $result);
  }

}

/**
 * Test class using the trait.
 */
class TestLayoutEntityHelperTrait {
  use LayoutEntityHelperTrait {
    getSectionStorageForEntity as public;
    originalEntityUsesDefaultStorage as public;
  }

}

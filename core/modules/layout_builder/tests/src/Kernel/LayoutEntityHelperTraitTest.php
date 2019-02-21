<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
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

  /**
   * Dataprovider for testGetSectionStorageForEntity().
   */
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
   * @covers ::getSectionStorageForEntity
   *
   * @dataProvider providerTestGetSectionStorageForEntity
   */
  public function testGetSectionStorageForEntity($entity_type_id, $values, $expected_context_keys) {
    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $section_storage_manager->load('')->willReturn(NULL);
    $section_storage_manager->findByContext(Argument::cetera())->will(function ($arguments) {
      return $arguments[0];
    });
    $this->container->set('plugin.manager.layout_builder.section_storage', $section_storage_manager->reveal());
    $entity = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->create($values);
    $entity->save();
    $class = new TestLayoutEntityHelperTrait();
    $result = $class->getSectionStorageForEntity($entity);
    $this->assertEquals($expected_context_keys, array_keys($result));
    if ($entity instanceof EntityViewDisplayInterface) {
      $this->assertEquals(EntityContext::fromEntity($entity), $result['display']);
    }
    elseif ($entity instanceof FieldableEntityInterface) {
      $this->assertEquals(EntityContext::fromEntity($entity), $result['entity']);
      $this->assertInstanceOf(Context::class, $result['view_mode']);
      $this->assertEquals('full', $result['view_mode']->getContextData()->getValue());

      $expected_display = EntityViewDisplay::collectRenderDisplay($entity, 'full');
      $this->assertInstanceOf(EntityContext::class, $result['display']);
      /** @var \Drupal\Core\Plugin\Context\EntityContext $display_entity_context */
      $display_entity_context = $result['display'];

      /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display_entity */
      $display_entity = $display_entity_context->getContextData()->getValue();
      $this->assertInstanceOf(LayoutBuilderEntityViewDisplay::class, $display_entity);

      $this->assertEquals('full', $display_entity->getMode());
      $this->assertEquals($expected_display->getEntityTypeId(), $display_entity->getEntityTypeId());
      $this->assertEquals($expected_display->getComponents(), $display_entity->getComponents());
      $this->assertEquals($expected_display->getThirdPartySettings('layout_builder'), $display_entity->getThirdPartySettings('layout_builder'));
    }
    else {
      throw new \UnexpectedValueException("Unexpected entity type.");
    }

  }

  public function providerTOriginalEntityUsesDefaultStorage() {
    return [
      [
        'updated' => 'default',
        'original' => 'override',
      ],
      FALSE,
      TRUE;
    ];
  }

  /**
   * @dataProvider providerTOriginalEntityUsesDefaultStorage
   */
  public function testOriginalEntityUsesDefaultStorage($expected_storages, $is_new, $has_original) {

    $entity = EntityTest::create([]);
    $entity->save();
    $original_entity = EntityTest::create(['name' => 'original']);
    $entity->original = $original_entity;

    $contexts = [
      'entity' => EntityContext::fromEntity($entity),
      'display'=> EntityContext::fromEntity(EntityViewDisplay::collectRenderDisplay($entity, 'full')),
      'view_mode' => new Context(new ContextDefinition('string'), 'full'),
    ];

    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $section_storage_manager->load('')->willReturn(NULL);
    $default_section_storage = $this->prophesize(DefaultsSectionStorageInterface::class)->reveal();
    $override_section_storage = $this->prophesize(OverridesSectionStorageInterface::class)->reveal();
    $section_storage_manager->findByContext(Argument::cetera())->will(function ($arguments) use ($default_section_storage, $override_section_storage) {
      $contexts = $arguments[0];
      if (isset($contexts['entity'])) {
        /** @var \Drupal\entity_test\Entity\EntityTest $entity */
        $entity = $contexts['entity']->getContextData()->getValue();
        if ($entity->getName() == 'original') {
          return $default_section_storage;
        }
        else {
          return $override_section_storage;
        }

      }

    });
    //$section_storage_manager->findByContext($original_entity, Argument::any())->willReturn($this->prophesize(DefaultsSectionStorageInterface::class)->reveal());
    $this->container->set('plugin.manager.layout_builder.section_storage', $section_storage_manager->reveal());
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

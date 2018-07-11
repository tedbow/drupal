<?php

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @coversDefaultClass \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage
 *
 * @group layout_builder
 */
class OverridesSectionStorageTest extends UnitTestCase {

  /**
   * The plugin.
   *
   * @var \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage
   */
  protected $plugin;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);

    $definition = new SectionStorageDefinition([
      'id' => 'overrides',
      'class' => OverridesSectionStorage::class,
    ]);
    $this->plugin = new OverridesSectionStorage([], 'overrides', $definition, $this->entityTypeManager->reveal(), $this->entityFieldManager->reveal());
  }

  /**
   * @covers ::extractIdFromRoute
   *
   * @dataProvider providerTestExtractIdFromRoute
   */
  public function testExtractIdFromRoute($expected, $value, array $defaults) {
    $result = $this->plugin->extractIdFromRoute($value, [], 'the_parameter_name', $defaults);
    $this->assertSame($expected, $result);
  }

  /**
   * Provides data for ::testExtractIdFromRoute().
   */
  public function providerTestExtractIdFromRoute() {
    $data = [];
    $data['with value, with layout'] = [
      'my_entity_type.entity_with_layout',
      'my_entity_type.entity_with_layout',
      [],
    ];
    $data['with value, without layout'] = [
      NULL,
      'my_entity_type',
      [],
    ];
    $data['empty value, populated defaults'] = [
      'my_entity_type.entity_with_layout',
      '',
      [
        'entity_type_id' => 'my_entity_type',
        'my_entity_type' => 'entity_with_layout',
      ],
    ];
    $data['empty value, empty defaults'] = [
      NULL,
      '',
      [],
    ];
    return $data;
  }

  /**
   * @covers ::getSectionListFromId
   *
   * @dataProvider providerTestGetSectionListFromId
   */
  public function testGetSectionListFromId($success, $expected_entity_type_id, $id) {
    $defaults['the_parameter_name'] = $id;

    if ($expected_entity_type_id) {
      $entity_storage = $this->prophesize(EntityStorageInterface::class);

      $entity_without_layout = $this->prophesize(FieldableEntityInterface::class);
      $entity_without_layout->hasField('layout_builder__layout')->willReturn(FALSE);
      $entity_without_layout->get('layout_builder__layout')->shouldNotBeCalled();
      $entity_storage->load('entity_without_layout')->willReturn($entity_without_layout->reveal());

      $entity_with_layout = $this->prophesize(FieldableEntityInterface::class);
      $entity_with_layout->hasField('layout_builder__layout')->willReturn(TRUE);
      $entity_with_layout->get('layout_builder__layout')->willReturn('the_return_value');
      $entity_storage->load('entity_with_layout')->willReturn($entity_with_layout->reveal());

      $this->entityTypeManager->getStorage($expected_entity_type_id)->willReturn($entity_storage->reveal());
    }
    else {
      $this->entityTypeManager->getStorage(Argument::any())->shouldNotBeCalled();
    }

    if (!$success) {
      $this->setExpectedException(\InvalidArgumentException::class);
    }

    $result = $this->plugin->getSectionListFromId($id);
    if ($success) {
      $this->assertEquals('the_return_value', $result);
    }
  }

  /**
   * Provides data for ::testGetSectionListFromId().
   */
  public function providerTestGetSectionListFromId() {
    $data = [];
    $data['with value, with layout'] = [
      TRUE,
      'my_entity_type',
      'my_entity_type.entity_with_layout',
    ];
    $data['with value, without layout'] = [
      FALSE,
      'my_entity_type',
      'my_entity_type.entity_without_layout',
    ];
    $data['empty value, empty defaults'] = [
      FALSE,
      NULL,
      '',
    ];
    return $data;
  }

  /**
   * @covers ::buildRoutes
   * @covers ::hasIntegerId
   * @covers ::getEntityTypes
   */
  public function testBuildRoutes() {
    $entity_types = [];

    $not_fieldable = $this->prophesize(EntityTypeInterface::class);
    $not_fieldable->entityClassImplements(FieldableEntityInterface::class)->willReturn(FALSE);
    $entity_types['not_fieldable'] = $not_fieldable->reveal();

    $no_view_builder = $this->prophesize(EntityTypeInterface::class);
    $no_view_builder->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE);
    $no_view_builder->hasViewBuilderClass()->willReturn(FALSE);
    $entity_types['no_view_builder'] = $no_view_builder->reveal();

    $no_canonical_link = $this->prophesize(EntityTypeInterface::class);
    $no_canonical_link->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE);
    $no_canonical_link->hasViewBuilderClass()->willReturn(TRUE);
    $no_canonical_link->hasLinkTemplate('canonical')->willReturn(FALSE);
    $entity_types['no_canonical_link'] = $no_canonical_link->reveal();
    $this->entityFieldManager->getFieldStorageDefinitions('no_canonical_link')->shouldNotBeCalled();

    $with_string_id = $this->prophesize(EntityTypeInterface::class);
    $with_string_id->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE);
    $with_string_id->hasViewBuilderClass()->willReturn(TRUE);
    $with_string_id->hasLinkTemplate('canonical')->willReturn(TRUE);
    $with_string_id->getLinkTemplate('canonical')->willReturn('/entity/{entity}');
    $with_string_id->id()->willReturn('with_string_id');
    $with_string_id->getKey('id')->willReturn('id');
    $entity_types['with_string_id'] = $with_string_id->reveal();
    $string_id = $this->prophesize(FieldStorageDefinitionInterface::class);
    $string_id->getType()->willReturn('string');
    $this->entityFieldManager->getFieldStorageDefinitions('with_string_id')->willReturn(['id' => $string_id->reveal()]);

    $with_integer_id = $this->prophesize(EntityTypeInterface::class);
    $with_integer_id->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE);
    $with_integer_id->hasViewBuilderClass()->willReturn(TRUE);
    $with_integer_id->hasLinkTemplate('canonical')->willReturn(TRUE);
    $with_integer_id->getLinkTemplate('canonical')->willReturn('/entity/{entity}');
    $with_integer_id->id()->willReturn('with_integer_id');
    $with_integer_id->getKey('id')->willReturn('id');
    $entity_types['with_integer_id'] = $with_integer_id->reveal();
    $integer_id = $this->prophesize(FieldStorageDefinitionInterface::class);
    $integer_id->getType()->willReturn('integer');
    $this->entityFieldManager->getFieldStorageDefinitions('with_integer_id')->willReturn(['id' => $integer_id->reveal()]);

    $this->entityTypeManager->getDefinitions()->willReturn($entity_types);

    $expected = [
      'layout_builder.overrides.with_string_id.view' => new Route(
        '/entity/{entity}/layout',
        [
          'entity_type_id' => 'with_string_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          'is_rebuilding' => FALSE,
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::layout',
          '_title_callback' => '\Drupal\layout_builder\Controller\LayoutBuilderController::title',
        ],
        [
          '_has_layout_section' => 'true',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_string_id' => ['type' => 'entity:with_string_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_string_id.save' => new Route(
        '/entity/{entity}/layout/save',
        [
          'entity_type_id' => 'with_string_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::saveLayout',
        ],
        [
          '_has_layout_section' => 'true',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_string_id' => ['type' => 'entity:with_string_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_string_id.cancel' => new Route(
        '/entity/{entity}/layout/cancel',
        [
          'entity_type_id' => 'with_string_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::cancelLayout',
        ],
        [
          '_has_layout_section' => 'true',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_string_id' => ['type' => 'entity:with_string_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_string_id.revert' => new Route(
        '/entity/{entity}/layout/revert',
        [
          'entity_type_id' => 'with_string_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          '_form' => '\Drupal\layout_builder\Form\RevertOverridesForm',
        ],
        [
          '_has_layout_section' => 'true',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_string_id' => ['type' => 'entity:with_string_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_integer_id.view' => new Route(
        '/entity/{entity}/layout',
        [
          'entity_type_id' => 'with_integer_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          'is_rebuilding' => FALSE,
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::layout',
          '_title_callback' => '\Drupal\layout_builder\Controller\LayoutBuilderController::title',
        ],
        [
          '_has_layout_section' => 'true',
          'with_integer_id' => '\d+',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_integer_id' => ['type' => 'entity:with_integer_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_integer_id.save' => new Route(
        '/entity/{entity}/layout/save',
        [
          'entity_type_id' => 'with_integer_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::saveLayout',
        ],
        [
          '_has_layout_section' => 'true',
          'with_integer_id' => '\d+',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_integer_id' => ['type' => 'entity:with_integer_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_integer_id.cancel' => new Route(
        '/entity/{entity}/layout/cancel',
        [
          'entity_type_id' => 'with_integer_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::cancelLayout',
        ],
        [
          '_has_layout_section' => 'true',
          'with_integer_id' => '\d+',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_integer_id' => ['type' => 'entity:with_integer_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
      'layout_builder.overrides.with_integer_id.revert' => new Route(
        '/entity/{entity}/layout/revert',
        [
          'entity_type_id' => 'with_integer_id',
          'section_storage_type' => 'overrides',
          'section_storage' => '',
          '_form' => '\Drupal\layout_builder\Form\RevertOverridesForm',
        ],
        [
          '_has_layout_section' => 'true',
          'with_integer_id' => '\d+',
        ],
        [
          'parameters' => [
            'section_storage' => ['layout_builder_tempstore' => TRUE],
            'with_integer_id' => ['type' => 'entity:with_integer_id'],
          ],
          '_layout_builder' => TRUE,
        ]
      ),
    ];

    $collection = new RouteCollection();
    $this->plugin->buildRoutes($collection);
    $this->assertEquals($expected, $collection->all());
    $this->assertSame(array_keys($expected), array_keys($collection->all()));
  }

  /**
   * @covers ::save
   *
   * @dataProvider providerTestSave
   */
  public function testSave($is_revisionable, $has_bundle_entity_type, $is_revisionable_bundle, $should_create_revision, $should_save_revision) {
    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $entity_type->isRevisionable()->willReturn($is_revisionable);
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->getEntityType()->willReturn($entity_type->reveal());
    $entity->bundle()->willReturn('BUNDLE_NAME');
    $section_list = $this->prophesize(LayoutSectionItemList::class);
    $section_list->getEntity()->willReturn($entity->reveal());
    $this->plugin->setSectionList($section_list->reveal());
    if ($is_revisionable) {
      $entity_type->id()->willReturn('ENTITY_TYPE_ID');
      $entity_type->getBundleEntityType()->willReturn($has_bundle_entity_type ? 'BUNDLE_TYPE_ID' : NULL);
      $entity_storage = $this->prophesize(EntityStorageInterface::class);
      if ($has_bundle_entity_type) {
        $bundle_type_class = $is_revisionable_bundle ? RevisionableEntityBundleInterface::class : ContentEntityStorageInterface::class;
        $entity_bundle_type = $this->prophesize($bundle_type_class);
        $entity_storage->load('BUNDLE_NAME')->willReturn($entity_bundle_type->reveal());
        if ($is_revisionable_bundle) {
          $entity_bundle_type->shouldCreateNewRevision()->willReturn($should_create_revision);
          $this->entityTypeManager->getStorage('BUNDLE_TYPE_ID')->willReturn($entity_storage->reveal());
          if ($should_create_revision) {
            $content_entity_storage = $this->prophesize(ContentEntityStorageInterface::class);
            $revision = $this->prophesize(ContentEntityInterface::class);
            $revision->save()->shouldBeCalled();
            $content_entity_storage->createRevision($entity)->willReturn($revision->reveal())->shouldBeCalled();
            $this->entityTypeManager->getStorage('ENTITY_TYPE_ID')->willReturn($content_entity_storage->reveal());
          }

        }
      }
    }
    if (!$should_save_revision) {
      $entity->save()->shouldBeCalled();
    }
    else {
      $entity->save()->shouldNotBeCalled();
    }
    $this->plugin->save();
  }

  /**
   * Provides data for ::testSave().
   */
  public function providerTestSave() {
    return [
      // The only case in which a new revision should be created is all
      // arguments to ::testSave() are TRUE.
      'revisionable' => [
        TRUE,
        TRUE,
        TRUE,
        TRUE,
        TRUE,
      ],
      'revisionable:no_new_revision' => [
        TRUE,
        TRUE,
        TRUE,
        FALSE,
        FALSE,
      ],
      'revisionable:no_bundle' => [
        TRUE,
        FALSE,
        NULL,
        NULL,
        FALSE,
      ],
      'non_revisionable' => [
        FALSE,
        FALSE,
        NULL,
        NULL,
        FALSE,
      ],
    ];
  }

}

<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;

/**
 * @coversDefaultClass \Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage
 *
 * @group layout_builder
 */
class DefaultsSectionStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_discovery',
    'layout_builder',
    'entity_test',
    'field',
    'system',
    'user',
  ];

  /**
   * The plugin.
   *
   * @var \Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', ['key_value_expire']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    $this->plugin = DefaultsSectionStorage::create($this->container, [], 'defaults', new SectionStorageDefinition());
  }

  /**
   * @covers ::access
   * @dataProvider providerTestAccess
   *
   * @group legacy
   * @expectedDeprecation @todo
   *
   * @param bool $expected
   *   The expected outcome of ::access().
   * @param string $operation
   *   The operation to pass to ::access().
   * @param bool $is_enabled
   *   Whether Layout Builder is enabled for this display.
   * @param array $section_data
   *   Data to store as the sections value for Layout Builder.
   */
  public function testAccess($expected, $operation, $is_enabled, array $section_data) {
    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    if ($is_enabled) {
      $display->enableLayoutBuilder();
    }
    $display
      ->setThirdPartySetting('layout_builder', 'sections', $section_data)
      ->save();

    $this->plugin->setContext('display', EntityContext::fromEntity($display));
    $result = $this->plugin->access($operation);
    $this->assertSame($expected, $result);
  }

  /**
   * Provides test data for ::testAccess().
   */
  public function providerTestAccess() {
    $section_data = [
      new Section('layout_onecol', [], [
        'first-uuid' => new SectionComponent('first-uuid', 'content', ['id' => 'foo']),
      ]),
    ];

    // Data provider values are:
    // - the expected outcome of the call to ::access()
    // - the operation
    // - whether Layout Builder has been enabled for this display
    // - whether this display has any section data.
    $data = [];
    $data['view, disabled, no data'] = [FALSE, 'view', FALSE, []];
    $data['view, enabled, no data'] = [TRUE, 'view', TRUE, []];
    $data['view, disabled, data'] = [FALSE, 'view', FALSE, $section_data];
    $data['view, enabled, data'] = [TRUE, 'view', TRUE, $section_data];
    return $data;
  }

  /**
   * @covers ::routingAccess
   * @dataProvider providerTestRoutingAccess
   *
   * @param \Drupal\Core\Access\AccessResultInterface $expected
   *   The expected outcome of ::routingAccess().
   * @param bool $is_enabled
   *   Whether Layout Builder is enabled for this display.
   * @param array $section_data
   *   Data to store as the sections value for Layout Builder.
   */
  public function testRoutingAccess(AccessResultInterface $expected, $is_enabled, array $section_data) {
    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    if ($is_enabled) {
      $display->enableLayoutBuilder();
    }
    $display
      ->setThirdPartySetting('layout_builder', 'sections', $section_data)
      ->save();

    $this->plugin->setContext('display', EntityContext::fromEntity($display));
    $result = $this->plugin->routingAccess();
    $this->assertEquals($expected, $result);
  }

  /**
   * Provides test data for ::testRoutingAccess().
   */
  public function providerTestRoutingAccess() {
    $section_data = [
      new Section('layout_onecol', [], [
        'first-uuid' => new SectionComponent('first-uuid', 'content', ['id' => 'foo']),
      ]),
    ];

    // Data provider values are:
    // - the expected outcome of the call to ::routingAccess()
    // - whether Layout Builder has been enabled for this display
    // - whether this display has any section data.
    $data = [];
    $data['disabled, no data'] = [
      AccessResult::neutral()->addCacheTags(['config:core.entity_view_display.entity_test.entity_test.default']),
      FALSE,
      [],
    ];
    $data['enabled, no data'] = [
      AccessResult::allowed()->addCacheTags(['config:core.entity_view_display.entity_test.entity_test.default']),
      TRUE,
      [],
    ];
    $data['disabled, data'] = [
      AccessResult::neutral()->addCacheTags(['config:core.entity_view_display.entity_test.entity_test.default']),
      FALSE,
      $section_data,
    ];
    $data['enabled, data'] = [
      AccessResult::allowed()->addCacheTags(['config:core.entity_view_display.entity_test.entity_test.default']),
      TRUE,
      $section_data,
    ];
    return $data;
  }

  /**
   * @covers ::getContexts
   */
  public function testGetContexts() {
    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->save();

    $context = EntityContext::fromEntity($display);
    $this->plugin->setContext('display', $context);

    $expected = ['display' => $context];
    $this->assertSame($expected, $this->plugin->getContexts());
  }

  /**
   * @covers ::getContextsDuringPreview
   */
  public function testGetContextsDuringPreview() {
    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->save();

    $context = EntityContext::fromEntity($display);
    $this->plugin->setContext('display', $context);

    $result = $this->plugin->getContextsDuringPreview();
    $this->assertEquals(['display', 'layout_builder.entity'], array_keys($result));

    $this->assertSame($context, $result['display']);

    $this->assertInstanceOf(EntityContext::class, $result['layout_builder.entity']);
    $result_value = $result['layout_builder.entity']->getContextValue();
    $this->assertInstanceOf(EntityTest::class, $result_value);
    $this->assertSame('entity_test', $result_value->bundle());
  }

  /**
   * @covers ::setSectionList
   *
   * @expectedDeprecation \Drupal\layout_builder\SectionStorageInterface::setSectionList() is deprecated in Drupal 8.7.0 and will be removed before Drupal 9.0.0. This method should no longer be used. The section list should be derived from context. See https://www.drupal.org/node/3016262.
   * @group legacy
   */
  public function testSetSectionList() {
    $section_list = $this->prophesize(SectionListInterface::class);
    $this->setExpectedException(\Exception::class, '\Drupal\layout_builder\SectionStorageInterface::setSectionList() must no longer be called. The section list should be derived from context. See https://www.drupal.org/node/3016262.');
    $this->plugin->setSectionList($section_list->reveal());
  }

}

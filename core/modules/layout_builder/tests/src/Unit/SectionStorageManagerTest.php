<?php

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Factory\FactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;
use Drupal\layout_builder\SectionStorage\SectionStorageManager;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\layout_builder\SectionStorage\SectionStorageManager
 *
 * @group layout_builder
 */
class SectionStorageManagerTest extends UnitTestCase {

  /**
   * The section storage manager.
   *
   * @var \Drupal\Tests\layout_builder\Unit\TestSectionStorageManager
   */
  protected $manager;

  /**
   * The plugin discovery.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface
   */
  protected $discovery;

  /**
   * The plugin factory.
   *
   * @var \Drupal\Component\Plugin\Factory\FactoryInterface
   */
  protected $factory;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->discovery = $this->prophesize(DiscoveryInterface::class);
    $this->factory = $this->prophesize(FactoryInterface::class);
    $cache = $this->prophesize(CacheBackendInterface::class);
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $this->contextHandler = $this->prophesize(ContextHandlerInterface::class);
    $this->manager = new TestSectionStorageManager($this->discovery->reveal(), $this->factory->reveal(), $cache->reveal(), $module_handler->reveal(), $this->contextHandler->reveal());
  }

  /**
   * @covers ::loadEmpty
   */
  public function testLoadEmpty() {
    $plugin = $this->prophesize(SectionStorageInterface::class);
    $this->factory->createInstance('the_plugin_id', [])->willReturn($plugin->reveal());

    $result = $this->manager->loadEmpty('the_plugin_id');
    $this->assertSame($plugin->reveal(), $result);
  }

  /**
   * @covers ::load
   */
  public function testLoad() {
    $plugin = $this->prophesize(SectionStorageInterface::class);
    $this->factory->createInstance('the_plugin_id', [])->willReturn($plugin->reveal());

    $contexts = [
      'the_context' => $this->prophesize(ContextInterface::class)->reveal(),
    ];

    $this->contextHandler->applyContextMapping($plugin, $contexts)->shouldBeCalled();

    $result = $this->manager->load('the_plugin_id', $contexts);
    $this->assertSame($plugin->reveal(), $result);
  }

  /**
   * @covers ::load
   */
  public function testLoadNull() {
    $plugin = $this->prophesize(SectionStorageInterface::class);
    $this->factory->createInstance('the_plugin_id', [])->willReturn($plugin->reveal());

    $contexts = [
      'the_context' => $this->prophesize(ContextInterface::class)->reveal(),
    ];

    $this->contextHandler->applyContextMapping($plugin, $contexts)->willThrow(new ContextException());

    $result = $this->manager->load('the_plugin_id', $contexts);
    $this->assertNull($result);
  }

  /**
   * @covers ::findDefinitions
   */
  public function testFindDefinitions() {
    $this->discovery->getDefinitions()->willReturn([
      'plugin1' => new SectionStorageDefinition(),
      'plugin2' => new SectionStorageDefinition(['weight' => -5]),
      'plugin3' => new SectionStorageDefinition(['weight' => -5]),
      'plugin4' => new SectionStorageDefinition(['weight' => 10]),
    ]);

    $expected = [
      'plugin2',
      'plugin3',
      'plugin1',
      'plugin4',
    ];
    $result = $this->manager->getDefinitions();
    $this->assertSame($expected, array_keys($result));
  }

  /**
   * @covers ::findByContext
   *
   * @dataProvider providerTestFindByContext
   */
  public function testFindByContext($access) {
    $contexts = [
      'foo' => new Context(new ContextDefinition('foo')),
    ];
    $definitions = [
      'no_access' => new SectionStorageDefinition(),
      'missing_contexts' => new SectionStorageDefinition(),
      'provider_access' => new SectionStorageDefinition(),
    ];
    $this->discovery->getDefinitions()->willReturn($definitions);

    $provider_access = $this->prophesize(SectionStorageInterface::class);
    $provider_access->access('test_operation')->willReturn($access);

    $no_access = $this->prophesize(SectionStorageInterface::class);
    $no_access->access('test_operation')->willReturn(FALSE);

    $missing_contexts = $this->prophesize(SectionStorageInterface::class);

    // Do not do any filtering based on context.
    $this->contextHandler->filterPluginDefinitionsByContexts($contexts, $definitions)->willReturnArgument(1);
    $this->contextHandler->applyContextMapping($no_access, $contexts)->shouldBeCalled();
    $this->contextHandler->applyContextMapping($provider_access, $contexts)->shouldBeCalled();
    $this->contextHandler->applyContextMapping($missing_contexts, $contexts)->willThrow(new ContextException());

    $this->factory->createInstance('no_access', [])->willReturn($no_access->reveal());
    $this->factory->createInstance('missing_contexts', [])->willReturn($missing_contexts->reveal());
    $this->factory->createInstance('provider_access', [])->willReturn($provider_access->reveal());

    $result = $this->manager->findByContext('test_operation', $contexts);
    if ($access) {
      $this->assertSame($provider_access->reveal(), $result);
    }
    else {
      $this->assertNull($result);
    }
  }

  /**
   * Provides test data for ::testFindByContext().
   */
  public function providerTestFindByContext() {
    $data = [];
    $data['true'] = [TRUE];
    $data['false'] = [FALSE];
    return $data;
  }

}

/**
 * Provides a test manager.
 */
class TestSectionStorageManager extends SectionStorageManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(DiscoveryInterface $discovery, FactoryInterface $factory, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ContextHandlerInterface $context_handler) {
    parent::__construct(new \ArrayObject(), $cache_backend, $module_handler, $context_handler);
    $this->discovery = $discovery;
    $this->factory = $factory;
  }

}

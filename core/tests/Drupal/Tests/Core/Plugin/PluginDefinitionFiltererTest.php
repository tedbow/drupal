<?php

namespace Drupal\Tests\Core\Plugin;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\PluginDefinitionFilterer;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Plugin\PluginDefinitionFilterer
 *
 * @group Plugin
 */
class PluginDefinitionFiltererTest extends UnitTestCase {

  /**
   * @covers ::get
   * @dataProvider providerTestGet
   */
  public function testGet($contexts, $expected) {
    // Start with two plugins.
    $definitions = [];
    $definitions['plugin1'] = ['id' => 'plugin1'];
    $definitions['plugin2'] = ['id' => 'plugin2'];

    $discovery = $this->prophesize(DiscoveryInterface::class);
    $discovery->getDefinitions()->willReturn($definitions);

    $type = 'the_type';
    $consumer = 'the_consumer';
    $extra = ['foo' => 'bar'];

    $context_handler = $this->prophesize(ContextHandlerInterface::class);
    // Remove the second plugin when context1 is provided.
    $context_handler->filterPluginDefinitionsByContexts(['context1' => 'fake context'], $definitions)
      ->willReturn(['plugin1' => $definitions['plugin1']]);
    // Remove the first plugin when no contexts are provided.
    $context_handler->filterPluginDefinitionsByContexts([], $definitions)
      ->willReturn(['plugin2' => $definitions['plugin2']]);

    // After context filtering, the alter hook will be invoked.
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $hooks = ["plugin_filter_{$type}", "plugin_filter_{$type}__{$consumer}"];
    $module_handler->alter($hooks, $expected, $extra, $consumer)->shouldBeCalled();

    $theme_manager = $this->prophesize(ThemeManagerInterface::class);
    $theme_manager->alter($hooks, $expected, $extra, $consumer)->shouldBeCalled();

    $definition_filterer = new PluginDefinitionFilterer($module_handler->reveal(), $theme_manager->reveal(), $context_handler->reveal());

    $result = $definition_filterer->get('the_type', $consumer, $discovery->reveal(), $contexts, $extra);
    $this->assertSame($expected, $result);
  }

  /**
   * Provides test data for ::testGet().
   */
  public function providerTestGet() {
    $data = [];
    $data['populated context'] = [
      ['context1' => 'fake context'],
      ['plugin1' => ['id' => 'plugin1']],
    ];
    $data['empty context'] = [
      [],
      ['plugin2' => ['id' => 'plugin2']],
    ];
    $data['null context'] = [
      NULL,
      [
        'plugin1' => ['id' => 'plugin1'],
        'plugin2' => ['id' => 'plugin2'],
      ],
    ];
    return $data;
  }

}

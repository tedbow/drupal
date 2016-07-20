<?php

namespace Drupal\Tests\outside_in\Unit;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\outside_in\OperationAwareFormInterface;
use Drupal\outside_in\Block\OutsideInBlockManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\outside_in\Block\OutsideInBlockManager
 * @group outside_in
 */
class OutsideInBlockManagerTest extends UnitTestCase {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $classResolver;

  /**
   * The manager being tested.
   *
   * @var \Drupal\outside_in\Block\OutsideInBlockManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->classResolver = $this->prophesize(ClassResolverInterface::class);
    $this->manager = new OutsideInBlockManager($this->classResolver->reveal());
  }

  /**
   * @covers ::getFormObject
   */
  public function testGetFormObject() {
    $plugin_form = $this->prophesize(PluginFormInterface::class);
    $expected = $plugin_form->reveal();

    $this->classResolver->getInstanceFromDefinition(get_class($expected))->willReturn($expected);

    $plugin = $this->prophesize(PluginInspectionInterface::class);
    $plugin->getPluginDefinition()->willReturn([
      'form' => [
        'standard_class' => get_class($expected),
      ],
    ]);

    $form_object = $this->manager->getFormObject($plugin->reveal(), 'standard_class');
    $this->assertSame($expected, $form_object);
  }

  /**
   * @covers ::getFormObject
   */
  public function testGetFormObjectUsingPlugin() {
    $this->classResolver->getInstanceFromDefinition(Argument::cetera())->shouldNotBeCalled();

    $plugin = $this->prophesize(PluginInspectionInterface::class)->willImplement(PluginFormInterface::class);
    $plugin->getPluginDefinition()->willReturn([
      'form' => [
        'default' => get_class($plugin->reveal()),
      ],
    ]);

    $form_object = $this->manager->getFormObject($plugin->reveal(), 'default');
    $this->assertSame($plugin->reveal(), $form_object);
  }

  /**
   * @covers ::getFormObject
   */
  public function testGetFormObjectOperationAware() {
    $plugin_form = $this->prophesize(PluginFormInterface::class)->willImplement(OperationAwareFormInterface::class);
    $plugin_form->setOperation('operation_aware')->shouldBeCalled();

    $expected = $plugin_form->reveal();

    $this->classResolver->getInstanceFromDefinition(get_class($expected))->willReturn($expected);

    $plugin = $this->prophesize(PluginInspectionInterface::class);
    $plugin->getPluginDefinition()->willReturn([
      'form' => [
        'operation_aware' => get_class($expected),
      ],
    ]);

    $form_object = $this->manager->getFormObject($plugin->reveal(), 'operation_aware');
    $this->assertSame($expected, $form_object);
  }

  /**
   * @covers ::getFormObject
   */
  public function testGetFormObjectDefinitionException() {
    $this->setExpectedException(InvalidPluginDefinitionException::class, 'The "the_plugin_id" plugin did not specify a "anything" form class');

    $plugin = $this->prophesize(PluginInspectionInterface::class);
    $plugin->getPluginId()->willReturn('the_plugin_id');
    $plugin->getPluginDefinition()->willReturn([]);

    $form_object = $this->manager->getFormObject($plugin->reveal(), 'anything');
    $this->assertSame(NULL, $form_object);
  }

  /**
   * @covers ::getFormObject
   */
  public function testGetFormObjectInvalidException() {
    $this->setExpectedException(InvalidPluginDefinitionException::class, 'The "the_plugin_id" plugin did not specify a valid "invalid" form class, must implement \Drupal\Core\Plugin\PluginFormInterface');

    $expected = new \stdClass();
    $this->classResolver->getInstanceFromDefinition(get_class($expected))->willReturn($expected);

    $plugin = $this->prophesize(PluginInspectionInterface::class);
    $plugin->getPluginId()->willReturn('the_plugin_id');
    $plugin->getPluginDefinition()->willReturn([
      'form' => [
        'invalid' => get_class($expected),
      ],
    ]);

    $form_object = $this->manager->getFormObject($plugin->reveal(), 'invalid');
    $this->assertSame(NULL, $form_object);
  }

}

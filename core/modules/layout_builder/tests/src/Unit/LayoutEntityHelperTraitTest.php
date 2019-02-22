<?php

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\layout_builder\LayoutEntityHelperTrait
 *
 * @group layout_builder
 */
class LayoutEntityHelperTraitTest extends UnitTestCase {

  /**
   * @covers ::isEntityUsingFieldOverride
   *
   * @dataProvider providerTestIsEntityUsingFieldOverride
   *
   * @expectedDeprecation \Drupal\layout_builder\LayoutEntityHelperTrait::isEntityUsingFieldOverride() is deprecated in Drupal 8.7.0 and will be removed before Drupal 9.0.0. Internal storage of overrides may change so the existence of the field does not necessarily guarantee an overridable entity. See https://www.drupal.org/node/3030609.
   *
   * @group legacy
   */
  public function testIsEntityUsingFieldOverride(EntityInterface $entity, $expected) {
    $test_class = new TestClass();
    $this->assertSame($expected, $test_class->isEntityUsingFieldOverride($entity));
  }

  /**
   * Dataprovider for testIsEntityUsingFieldOverride().
   */
  public function providerTestIsEntityUsingFieldOverride() {
    $data['non fieldable entity'] = [
      $this->prophesize(EntityInterface::class)->reveal(),
      FALSE,
    ];
    $fieldable_entity = $this->prophesize(FieldableEntityInterface::class);
    $fieldable_entity->hasField(OverridesSectionStorage::FIELD_NAME)->willReturn(FALSE);
    $data['fieldable entity without layout field'] = [
      $fieldable_entity->reveal(),
      FALSE,
    ];
    $entity_using_field = $this->prophesize(FieldableEntityInterface::class);
    $entity_using_field->hasField(OverridesSectionStorage::FIELD_NAME)->willReturn(TRUE);
    $data['fieldable entity with layout field'] = [
      $entity_using_field->reveal(),
      TRUE,
    ];
    return $data;
  }

  /**
   * @covers ::getInlineBlockComponents
   */
  public function testGetInlineBlockComponents() {
    $components = [];

    $non_derivative_component = $this->prophesize(SectionComponent::class);
    $non_derivative_component->getPlugin()->willReturn($this->prophesize(PluginInspectionInterface::class)->reveal());
    $components[] = $non_derivative_component->reveal();

    $derivative_non_inline_component = $this->prophesize(SectionComponent::class);
    $plugin = $this->prophesize(DerivativeInspectionInterface::class);
    $plugin->getBaseId()->willReturn('some_other_base_id_which_we_do_not_care_about_but_it_is_nothing_personal');
    $derivative_non_inline_component->getPlugin()->willReturn($plugin);
    $components[] = $derivative_non_inline_component->reveal();

    $inline_component = $this->prophesize(SectionComponent::class);
    $inline_plugin = $this->prophesize(DerivativeInspectionInterface::class);
    $inline_plugin->getBaseId()->willReturn('inline_block');
    $inline_component->getPlugin()->willReturn($inline_plugin);
    $inline_component = $inline_component->reveal();
    $components[] = $inline_component;

    $section = $this->prophesize(Section::class);
    $section->getComponents()->willReturn($components);
    $section = $section->reveal();
    // Add the section twice to ensure all sections are looped through.
    $sections = [$section, $section];
    $test_class = new TestClass();
    $this->assertSame([$inline_component, $inline_component], $test_class->getInlineBlockComponents($sections));

  }


}

/**
 * Test class using the trait.
 */
class TestClass {
  use LayoutEntityHelperTrait {
    isEntityUsingFieldOverride as public;
    getInlineBlockComponents as public;
  }

}

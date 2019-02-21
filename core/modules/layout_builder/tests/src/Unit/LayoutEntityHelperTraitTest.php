<?php

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
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
    $non_fieldable_entity = $this->prophesize(EntityInterface::class)->reveal();
    $data[] = [
      $non_fieldable_entity,
      FALSE,
    ];
    $fieldable_entity = $this->prophesize(FieldableEntityInterface::class);
    $fieldable_entity->hasField(OverridesSectionStorage::FIELD_NAME)->willReturn(FALSE);
    $data[] = [
      $fieldable_entity->reveal(),
      FALSE,
    ];
    $entity_using_field = $this->prophesize(FieldableEntityInterface::class);
    $entity_using_field->hasField(OverridesSectionStorage::FIELD_NAME)->willReturn(TRUE);
    $data[] = [
      $entity_using_field->reveal(),
      TRUE,
    ];
    return $data;
  }

}

/**
 * Test class using the trait.
 */
class TestClass {
  use LayoutEntityHelperTrait {
    isEntityUsingFieldOverride as public;
  }

}

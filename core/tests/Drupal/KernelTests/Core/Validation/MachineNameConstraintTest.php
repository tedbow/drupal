<?php

namespace Drupal\KernelTests\Core\Validation;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the machine name constraint.
 *
 * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\MachineNameConstraint
 * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\MachineNameConstraintValidator
 *
 * @group Validation
 */
class MachineNameConstraintTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['config_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig('config_test');
  }

  /**
   * Test the validation.
   *
   * @dataProvider validationsDataProvider
   */
  public function testValidation($value, $violation_count) {
    $typed_config_manager = \Drupal::service('config.typed');
    $typed_config = $typed_config_manager->get('config_test.validation');
    $typed_config->get('machine_name')->setValue($value);
    $violations = $typed_config->validate();
    $this->assertCount($violation_count, $violations);
  }

  /**
   * Data provider for ::testValidation.
   */
  public function validationsDataProvider() {
    return [
      'Valid machine name' => [
        'foo_machine_name',
        0,
      ],
      'Invalid machine name' => [
        'invalid machine name',
        1,
      ],
    ];
  }
}

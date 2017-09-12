<?php

namespace Drupal\config_test\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures that the config test protected property cannot be changed.
 *
 * @Constraint(
 *   id = "ConfigTestProtectedProperty",
 *   label = @Translation("Protected property", context = "Validation"),
 * )
 */
class ConfigTestProtectedProperty extends Constraint {

  public $message = 'Protected property cannot be changed.';

}

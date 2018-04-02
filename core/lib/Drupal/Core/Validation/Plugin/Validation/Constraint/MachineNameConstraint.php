<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates a machine name.
 *
 * @Constraint(
 *   id = "MachineName",
 *   label = @Translation("Machine name", context = "Validation"),
 * )
 */
class MachineNameConstraint extends Constraint {

  public $message = 'This value should be a valid machine name. Valid machine names contain only alphanumeric characters and underscores.';

  public $replacePattern = '[^a-z0-9_]+';

}

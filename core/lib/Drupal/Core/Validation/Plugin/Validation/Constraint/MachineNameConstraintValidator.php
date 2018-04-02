<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Machine name constraint validator.
 */
class MachineNameConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (preg_match('@' . $constraint->replacePattern . '@', $value)) {
      $this->context->addViolation($constraint->message);
    }
  }

}

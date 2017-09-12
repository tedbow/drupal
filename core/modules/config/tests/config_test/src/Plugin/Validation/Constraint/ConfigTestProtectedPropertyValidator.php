<?php

namespace Drupal\config_test\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConfigTestProtectedPropertyValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!empty($value)) {
      $this->context->addViolation($constraint->message);
    }
  }

}

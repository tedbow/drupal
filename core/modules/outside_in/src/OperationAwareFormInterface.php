<?php

namespace Drupal\outside_in;

/**
 * Interface for forms that are aware of what operation they are performing.
 */
interface OperationAwareFormInterface {

  /**
   * Sets the operation for this form.
   *
   * @param string $operation
   *   The name of the current operation.
   *
   * @return $this
   */
  public function setOperation($operation);

}

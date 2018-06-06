<?php

namespace Drupal\block_content_test\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

class TestSelection extends DefaultSelection {

  /**
   * The test mode.
   *
   * @var string
   */
  protected $testMode;

  /**
   * Sets the test mode.
   *
   * @param string $testMode
   *   The test mode.
   */
  public function setTestMode($testMode) {
    $this->testMode = $testMode;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    switch ($this->testMode) {
      case 'reusable_condition_false':
        $query->condition("reusable", 0);
        break;

      case 'reusable_condition_exists':
        $query->exists('reusable');
        break;

      case 'reusable_condition_group_false':
        $query->andConditionGroup()
          ->condition("reusable", 0)
          ->exists('type');
    }
    return $query;
  }

}

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
        $group = $query->andConditionGroup()
          ->condition("reusable", 0)
          ->exists('type');
        $query->condition($group);
        break;

      case 'reusable_condition_group_true':
        $group = $query->andConditionGroup()
          ->condition("reusable", 1)
          ->exists('type');
        $query->condition($group);
        break;

      case 'reusable_condition_nested_group_false':
        $query->exists('type');
        $sub_group = $query->andConditionGroup()
          ->condition("reusable", 0)
          ->exists('type');
        $group = $query->andConditionGroup()
          ->exists('type')
          ->condition($sub_group);
        $query->condition($group);
        break;

      case 'reusable_condition_nested_group_true':
        $query->exists('type');
        $sub_group = $query->andConditionGroup()
          ->condition("reusable", 1)
          ->exists('type');
        $group = $query->andConditionGroup()
          ->exists('type')
          ->condition($sub_group);
        $query->condition($group);
        break;
    }
    return $query;
  }

}

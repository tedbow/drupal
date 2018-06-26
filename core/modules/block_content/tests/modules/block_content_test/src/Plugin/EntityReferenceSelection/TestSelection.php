<?php

namespace Drupal\block_content_test\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Test EntityReferenceSelection that adds various parent entity conditions.
 */
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
    if (strpos($this->testMode, 'parent_entity_id') === 0) {
      $field = 'parent_entity_id';
    }
    else {
      $field = 'parent_entity_type';
    }
    switch ($this->testMode) {
      case "{$field}_condition_false":
        $query->notExists($field);
        break;

      case "{$field}_condition_group_false":
        $group = $query->andConditionGroup()
          ->notExists($field)
          ->exists('type');
        $query->condition($group);
        break;

      case "{$field}_condition_group_true":
        $group = $query->andConditionGroup()
          ->exists($field)
          ->exists('type');
        $query->condition($group);
        break;

      case "{$field}_condition_nested_group_false":
        $query->exists('type');
        $sub_group = $query->andConditionGroup()
          ->notExists($field)
          ->exists('type');
        $group = $query->andConditionGroup()
          ->exists('type')
          ->condition($sub_group);
        $query->condition($group);
        break;

      case "{$field}_condition_nested_group_true":
        $query->exists('type');
        $sub_group = $query->andConditionGroup()
          ->exists($field)
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

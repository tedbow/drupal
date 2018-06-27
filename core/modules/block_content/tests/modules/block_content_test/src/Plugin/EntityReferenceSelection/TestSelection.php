<?php

namespace Drupal\block_content_test\Plugin\EntityReferenceSelection;

use Drupal\block_content\BlockContentInterface;
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
  protected $field;

  protected $conditionType;

  protected $hasParent;

  /**
   * Sets the test mode.
   *
   * @param $field
   * @param $condition_type
   * @param $has_parent
   */
  public function setTestMode($field = NULL, $condition_type = NULL, $has_parent = NULL) {
    $this->field = $field;
    $this->conditionType = $condition_type;
    $this->hasParent = $has_parent;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    if ($this->field) {
      //print "field:$field test_case:$test_case\n";
      switch ($this->conditionType) {
        case 'base':
          $add_condition = $query;
          break;

        case 'group':
          $group = $query->andConditionGroup()
            ->exists('type');
          $add_condition = $group;
          $query->condition($group);
          break;

        case "nested_group":
          $query->exists('type');
          $sub_group = $query->andConditionGroup()
            ->exists('type');
          $add_condition = $sub_group;
          $group = $query->andConditionGroup()
            ->exists('type')
            ->condition($sub_group);
          $query->condition($group);
          break;
      }
      if ($this->field === 'parent_status') {
        if ($this->hasParent) {
          $add_condition->condition($this->field, BlockContentInterface::PARENT_ACTIVE);
        }
        else {
          $add_condition->condition($this->field, BlockContentInterface::PARENT_NONE);
        }
      }
      else {
        if ($this->hasParent) {
          $add_condition->exists($this->field);
        }
        else {
          $add_condition->notExists($this->field);
        }
      }

    }
    return $query;
  }

}

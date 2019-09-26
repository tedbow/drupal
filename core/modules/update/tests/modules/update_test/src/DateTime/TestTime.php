<?php

namespace Drupal\update_test\DateTime;

use Drupal\Component\Datetime\Time;

class TestTime extends Time {

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    if ($mock_date = \Drupal::state()->get('update_test.mock_date', NULL)) {
      return \DateTime::createFromFormat('m/d/Y', $mock_date)->getTimestamp();
    }
    return parent::getRequestTime();
  }

}

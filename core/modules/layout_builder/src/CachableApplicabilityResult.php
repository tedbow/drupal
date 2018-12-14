<?php

namespace Drupal\layout_builder;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

class CachableApplicabilityResult implements RefinableCacheableDependencyInterface {
  use RefinableCacheableDependencyTrait;


  /**
   * @var bool
   */
  protected $isApplicable;

  /**
   * CachableApplicabilityResult constructor.
   *
   * @param bool $isApplicable
   */
  public function __construct($isApplicable) {
    $this->isApplicable = $isApplicable;
  }

  /**
   * @return bool
   */
  public function isApplicable() {
    return $this->isApplicable;
  }

}

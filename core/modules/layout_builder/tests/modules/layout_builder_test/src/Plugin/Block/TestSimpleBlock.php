<?php

namespace Drupal\layout_builder_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Simple Block' block.
 *
 * @Block(
 *   id = "test_simple_block",
 *   admin_label = @Translation("Simple Block")
 * )
 */
class TestSimpleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return ['#markup' => 'Simple Block Build'];
  }

}

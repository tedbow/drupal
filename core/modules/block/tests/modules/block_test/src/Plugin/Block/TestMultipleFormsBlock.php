<?php

namespace Drupal\block_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with multiple forms.
 *
 * @Block(
 *   id = "test_multiple_forms_block",
 *   form = {
 *     "secondary" = "\Drupal\block_test\Form\SecondaryBlockForm"
 *   },
 *   admin_label = @Translation("Multiple forms test block")
 * )
 */
class TestMultipleFormsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [];
  }

}

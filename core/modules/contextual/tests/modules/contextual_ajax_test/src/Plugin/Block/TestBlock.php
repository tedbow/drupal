<?php
/**
 * Created by PhpStorm.
 * User: ted.bowman
 * Date: 7/12/16
 * Time: 3:27 PM
 */

namespace Drupal\contextual_ajax_test\Plugin\Block;


use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Powered by Drupal' block.
 *
 * @Block(
 *   id = "ajax_test_block",
 *   admin_label = @Translation("Test block")
 * )
 */
class TestBlock extends BlockBase{
  public function build() {
    return [
      '#markup' => 'This content is of no importance.',
      '#attached' => [
        'library' =>  ['core/drupal.ajax'],
      ],
    ];
  }


}

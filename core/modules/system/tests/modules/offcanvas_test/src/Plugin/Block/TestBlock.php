<?php
/**
 * Created by PhpStorm.
 * User: ted.bowman
 * Date: 7/12/16
 * Time: 3:27 PM
 */

namespace Drupal\offcanvas_test\Plugin\Block;


use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides a 'Powered by Drupal' block.
 *
 * @Block(
 *   id = "offcanvas_links_block",
 *   admin_label = @Translation("Offcanvas test block")
 * )
 */
class TestBlock extends BlockBase{
  public function build() {
    return [
      'offcanvas_link' => [
        '#title' => $this->t('Click Me!'),
        '#type' => 'link',
        '#url' => Url::fromRoute('router_test.2'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'offcanvas',
        ]
      ],
      '#attached' => [
        'library' => ['core/drupal.offcanvas'],
      ],
    ];
  }

}

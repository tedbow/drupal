<?php

namespace Drupal\offcanvas_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides a 'Powered by Drupal' block.
 *
 * @Block(
 *   id = "offcanvas_links_block",
 *   form = {
 *     "offcanvas" = "\Drupal\offcanvas_test\Form\OffCanvasForm"
 *   },
 *   admin_label = @Translation("Off-canvas test block")
 * )
 */
class TestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      'offcanvas_link_1' => [
        '#title' => $this->t('Click Me 1!'),
        '#type' => 'link',
        '#url' => Url::fromRoute('offcanvas_test.thing1'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'offcanvas',
        ],
      ],
      'offcanvas_link_2' => [
        '#title' => $this->t('Click Me 2!'),
        '#type' => 'link',
        '#url' => Url::fromRoute('offcanvas_test.thing2'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'offcanvas',
        ],
      ],
      '#attached' => [
        'library' => [
          'outside_in/drupal.off_canvas',
        ],
      ],
    ];
  }

}

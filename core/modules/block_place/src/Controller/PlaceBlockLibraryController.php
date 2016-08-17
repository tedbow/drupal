<?php


namespace Drupal\block_place\Controller;


use Drupal\block\Controller\BlockLibraryController;
use Symfony\Component\HttpFoundation\Request;

class PlaceBlockLibraryController extends BlockLibraryController{

  /**
   * {@inheritdoc}
   */
  public function listBlocks(Request $request, $theme) {
    $build = parent::listBlocks($request, $theme);
    // Alter all 'Place Block' links to use the Offcanvas tray.
    if (isset($build['blocks']['#rows'])) {
      foreach ($build['blocks']['#rows'] as &$row) {
        if (isset($row['operations']['data']['#links']['add'])) {
          $row['operations']['data']['#links']['add']['attributes']['data-dialog-type'] = 'offcanvas';
        }
      }
    }
    return $build;
  }

}

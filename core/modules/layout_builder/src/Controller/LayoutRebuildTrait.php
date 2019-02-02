<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\OpenOffCanvasDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides AJAX responses to rebuild the Layout Builder.
 *
 * @internal
 */
trait LayoutRebuildTrait {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage) {
    $response = $this->rebuildLayout($section_storage);
    if ($this->reopenOverview()) {
      /** @var \Drupal\layout_builder\Controller\OverViewController $overView */
      $overView = $this->classResolver->getInstanceFromDefinition(OverViewController::class);
      $response->addCommand(new OpenOffCanvasDialogCommand('Overview', $overView->overview($section_storage)));
    }
    else {
      $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    }

    return $response;
  }

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function rebuildLayout(SectionStorageInterface $section_storage) {
    $response = new AjaxResponse();
    $layout_controller = $this->classResolver->getInstanceFromDefinition(LayoutBuilderController::class);
    $layout = $layout_controller->layout($section_storage, TRUE);
    $response->addCommand(new ReplaceCommand('#layout-builder', $layout));
    return $response;
  }

  /**
   * Determines whether to reopen the overview.
   *
   * @return bool
   *   TRUE if the overview should be reopened, otherwise FALSE.
   */
  protected function reopenOverview() {
    return !empty(\Drupal::request()->query->get('open-overview'));
  }

  /**
   * Gets route options to reopen overview.
   *
   * @param bool $always_reopen
   *   Always reopen the overview.
   *
   * @return array
   *   Route options.
   */
  protected function getOverviewOptions($always_reopen = FALSE) {
    if ($always_reopen || $this->reopenOverview()) {
      return ['query' => ['open-overview' => TRUE]];
    }
    return [];
  }

}

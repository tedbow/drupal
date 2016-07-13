<?php

namespace Drupal\outside_in\Tests\Ajax;

use Drupal\ajax_test\Controller\AjaxTestController;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\system\Tests\Ajax\AjaxTestBase;

/**
 * Performs tests on opening and manipulating dialogs via AJAX commands.
 *
 * @group Outside In
 */
class OffCanvasDialogTest extends AjaxTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('ajax_test');

  /**
   * Test sending AJAX requests to open and manipulate offcanvas dialog.
   */
  public function testDialog() {
    $this->drupalLogin($this->drupalCreateUser(array('administer contact forms')));
    // Ensure the elements render without notices or exceptions.
    $this->drupalGet('ajax-test/dialog');

    // Set up variables for this test.
    $dialog_renderable = AjaxTestController::dialogContents();
    $dialog_contents = \Drupal::service('renderer')->renderRoot($dialog_renderable);

    $offcanvas_expected_response = array(
      'command' => 'openOffCanvas',
      'selector' => '#drupal-offcanvas',
      'settings' => NULL,
      'data' => $dialog_contents,
      'dialogOptions' => array(
        'modal' => FALSE,
        'title' => 'AJAX Dialog contents',
      ),
    );

    // Emulate going to the JS version of the page and check the JSON response.
    $ajax_result = $this->drupalGetAjax('ajax-test/dialog-contents', array('query' => array(MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_offcanvas')));
    $this->assertEqual($offcanvas_expected_response, $ajax_result[3], 'Modal dialog JSON response matches.');
  }

}

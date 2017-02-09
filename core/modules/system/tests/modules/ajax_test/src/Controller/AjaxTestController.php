<?php

namespace Drupal\ajax_test\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides content for dialog tests.
 */
class AjaxTestController {

  /**
   * Example content for dialog testing.
   *
   * @return array
   *   Renderable array of AJAX dialog contents.
   */
  public static function dialogContents() {
    // This is a regular render array; the keys do not have special meaning.
    $content = array(
      '#title' => '<em>AJAX Dialog & contents</em>',
      'content' => array(
        '#markup' => 'Example message',
      ),
      'cancel' => array(
        '#type' => 'link',
        '#title' => 'Cancel',
        '#url' => Url::fromRoute('<front>'),
        '#attributes' => array(
          // This is a special class to which JavaScript assigns dialog closing
          // behavior.
          'class' => array('dialog-cancel'),
        ),
      ),
    );

    return $content;
  }

  /**
   * Example content for testing whether response should be wrapped in div.
   *
   * @param string $type
   *   Type of response. Either 'wrapped' or 'not-wrapped'.
   *
   * @return array
   *   Renderable array of AJAX dialog contents.
   */
  public static function renderTypes($type) {
    // This is a regular render array; the keys do not have special meaning.
    switch ($type) {
      case 'wrapped':
        $markup = '<div>wrapped</div>';
        break;

      case 'not-wrapped':
        $markup = 'not-wrapped';
        break;

      case 'both':
        $markup = 'outside<div>inside</div>';
        break;
    }
    $content = [
      '#title' => '<em>AJAX Dialog & contents</em>',
      'content' => [
        '#markup' => $markup,
      ],
    ];

    return $content;
  }

  public function insertTest() {
    $build['links'] = [
      'ajax_target' => [
        '#markup' => '<div id="ajax-target">Target</div>',
      ],
      'links' => [
          '#theme' => 'links',
          '#links' => [
            'link1' => [
              'title' => 'Link 2 (pre-wrapped)',
              'url' => Url::fromRoute('ajax_test.ajax_render_types', ['type' => 'wrapped']),
              'attributes' => [
                'class' => ['ajax-insert'],
              ],
              '#attached' => ['library' => ['ajax_test/ajax_insert']],
            ],
            'link2' => [
              'title' => 'Link 3 (not wrapped)',
              'url' => Url::fromRoute('ajax_test.ajax_render_types', ['type' => 'not-wrapped']),
              'attributes' => [
                'class' => ['ajax-insert'],
              ],
              '#attached' => ['library' => ['ajax_test/ajax_insert']],
            ],
            'link3' => [
              'title' => 'Link 3 (both)',
              'url' => Url::fromRoute('ajax_test.ajax_render_types', ['type' => 'both']),
              'attributes' => [
                'class' => ['ajax-insert'],
              ],
              '#attached' => ['library' => ['ajax_test/ajax_insert']],
            ],
          ],
          '#attached' => ['library' => ['ajax_test/ajax_insert']],
      ],

    ];

    return $build;
  }

  /**
   * Returns a render array that will be rendered by AjaxRenderer.
   *
   * Verifies that the response incorporates JavaScript settings generated
   * during the page request by adding a dummy setting.
   */
  public function render() {
    return [
      '#attached' => [
        'library' => [
          'core/drupalSettings',
        ],
        'drupalSettings' => [
          'ajax' => 'test',
        ],
      ],
    ];
  }

  /**
   * Returns the used theme.
   */
  public function theme() {
    return [
      '#markup' => 'Current theme: ' . \Drupal::theme()->getActiveTheme()->getName(),
    ];
  }

  /**
   * Returns an AjaxResponse; settings command set last.
   *
   * Helps verifying AjaxResponse reorders commands to ensure correct execution.
   */
  public function order() {
    $response = new AjaxResponse();
    // HTML insertion command.
    $response->addCommand(new HtmlCommand('body', 'Hello, world!'));
    $build['#attached']['library'][] = 'ajax_test/order';
    $response->setAttachments($build['#attached']);

    return $response;
  }

  /**
   * Returns an AjaxResponse with alert command.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The JSON response object.
   */
  public function renderError(Request $request) {
    $message = '';
    $query = $request->query;
    if ($query->has('message')) {
      $message = $query->get('message');
    }
    $response = new AjaxResponse();
    $response->addCommand(new AlertCommand($message));
    return $response;
  }

  /**
   * Returns a render array of form elements and links for dialog.
   */
  public function dialog() {
    // Add two wrapper elements for testing non-modal dialogs. Modal dialogs use
    // the global drupal-modal wrapper by default.
    $build['dialog_wrappers'] = array('#markup' => '<div id="ajax-test-dialog-wrapper-1"></div><div id="ajax-test-dialog-wrapper-2"></div>');

    // Dialog behavior applied to a button.
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\ajax_test\Form\AjaxTestDialogForm');

    // Dialog behavior applied to a #type => 'link'.
    $build['link'] = array(
      '#type' => 'link',
      '#title' => 'Link 1 (modal)',
      '#url' => Url::fromRoute('ajax_test.dialog_contents'),
      '#attributes' => array(
        'class' => array('use-ajax'),
        'data-dialog-type' => 'modal',
      ),
    );

    // Dialog behavior applied to links rendered by links.html.twig.
    $build['links'] = array(
      '#theme' => 'links',
      '#links' => array(
        'link2' => array(
          'title' => 'Link 2 (modal)',
          'url' => Url::fromRoute('ajax_test.dialog_contents'),
          'attributes' => array(
            'class' => array('use-ajax'),
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(array(
              'width' => 400,
            ))
          ),
        ),
        'link3' => array(
          'title' => 'Link 3 (non-modal)',
          'url' => Url::fromRoute('ajax_test.dialog_contents'),
          'attributes' => array(
            'class' => array('use-ajax'),
            'data-dialog-type' => 'dialog',
            'data-dialog-options' => json_encode(array(
              'target' => 'ajax-test-dialog-wrapper-1',
              'width' => 800,
            ))
          ),
        ),
        'link4' => array(
          'title' => 'Link 4 (close non-modal if open)',
          'url' => Url::fromRoute('ajax_test.dialog_close'),
          'attributes' => array(
            'class' => array('use-ajax'),
            'data-dialog-type' => 'modal',
          ),
        ),
        'link5' => array(
          'title' => 'Link 5 (form)',
          'url' => Url::fromRoute('ajax_test.dialog_form'),
          'attributes' => array(
            'class' => array('use-ajax'),
            'data-dialog-type' => 'modal',
          ),
        ),
        'link6' => array(
          'title' => 'Link 6 (entity form)',
          'url' => Url::fromRoute('contact.form_add'),
          'attributes' => array(
            'class' => array('use-ajax'),
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(array(
              'width' => 800,
              'height' => 500,
            ))
          ),
        ),
        'link7' => array(
          'title' => 'Link 7 (non-modal, no target)',
          'url' => Url::fromRoute('ajax_test.dialog_contents'),
          'attributes' => array(
            'class' => array('use-ajax'),
            'data-dialog-type' => 'dialog',
            'data-dialog-options' => json_encode(array(
              'width' => 800,
            ))
          ),
        ),
        'link8' => [
          'title' => 'Link 8 (ajax)',
          'url' => Url::fromRoute('ajax_test.admin.theme'),
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode([
              'width' => 400,
            ]),
          ],
        ],
        'link9' => [
          'title' => 'Link 9 (ajax, wrapped response)',
          'url' => Url::fromRoute('ajax_test.ajax_render_types', ['type' => 'wrapped']),
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',

          ],
        ],
        'link10' => [
          'title' => 'Link 10 (ajax, not-wrapped response)',
          'url' => Url::fromRoute('ajax_test.ajax_render_types', ['type' => 'not-wrapped']),
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',

          ],
        ],
      ),
    );

    return $build;
  }

  /**
   * Returns an AjaxResponse with command to close dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The JSON response object.
   */
  public function dialogClose() {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand('#ajax-test-dialog-wrapper-1'));
    return $response;
  }

}

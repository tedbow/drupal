/**
 * @file
 *  Testing behavior for JSWebAssertTest.
 */

(($, Drupal) => {
  /**
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Makes changes in the DOM to be able to test the completion of AJAX in assertWaitOnAjaxRequest.
   */
  Drupal.behaviors.js_webassert_test_wait_for_ajax_request = {
    attach() {
      $('#edit-test-assert-no-element-after-wait-pass').on('click', (e) => {
        e.preventDefault();
        setTimeout(() => {
          $('#edit-test-assert-no-element-after-wait-pass').remove();
        }, 500);
      });
    },
  };
})(jQuery, Drupal);

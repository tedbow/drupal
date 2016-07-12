/**
 * @file
 * Drupal's Off Canvas library.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Command to open an off canvas element.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   *
   * @return {bool|undefined}
   *   Returns false if there was no selector property in the response object.
   */
  Drupal.AjaxCommands.prototype.openSidebar = function (ajax, response, status) {
    // Set animation duration and get #page-wrapper width.
    var animationDuration = 600;
    var $pageWrapper = $('#page-wrapper');
    var pageWidth = $pageWrapper.width();

    var $page = $('#page');

    // Create elements used in offcanvas construction.
    var $title = $('<h1 />', {text: 'My New Title'});
    var $offcanvasContent = $('<div />', {class: 'content', html: 'My New Title'});
    var $offcanvasClose = $('<span />', {class: 'offcanvasClose', text: 'x'}).click(function () {
      $offcanvasWrapper.animate({right: -(pageWidth * .2)}, {duration: animationDuration, queue: false});
      $page
        .removeClass('offCanvasDisplay')
        .animate({'margin-right': 0}, {duration: animationDuration, queue: false, complete: function () {
          // Remove Off Canvas element, and set display state variable.
          Drupal.offCanvas.visible = false;
          $offcanvasWrapper.remove();
        }});
    });

    // Construct Off Canvas wrapper
    var $offcanvasWrapper = $('<div />', {
      id: 'offcanvas',
      css: {
        width: pageWidth * .2,
        right: -(pageWidth * .2)
      }
    }).append([$offcanvasClose, $title, $offcanvasContent]);

    // Append Off Canvas wrapper to the 'page'
    $pageWrapper.append($offcanvasWrapper);

    // Animate $page and $offcanvasWrapper to simulate a slide in effect
    $offcanvasWrapper
      .animate({right: 0}, {duration: animationDuration, queue: false});
    $page
      .addClass('offCanvasDisplay')
      .animate({
        'margin-right': pageWidth * .2
      }, {duration: animationDuration, queue: false, complete: function () {
        // Set the offCanvas visible state.
        Drupal.offCanvas = {
          visible: true
        };
      }});

    return false;
  };

})(jQuery, Drupal);

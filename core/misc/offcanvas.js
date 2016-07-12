/**
 * @file
 * Drupal's sidebar library.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Command to open a dialog.
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
    var animationDuration = 600;
    var pageWidth = $('#page-wrapper').width();

    var $page = $('#page');
    var $sidebar = $('<div />', {
      id: 'offcanvas',
      html: response.data,
      css: {
        width: pageWidth * .2,
        right: -(pageWidth * .2)
      }
    });
    $('#page-wrapper').append($sidebar);

    $sidebar
      .animate({right: 0}, {duration: animationDuration, queue: false});
    $page
      .addClass('offCanvasDisplay')
      .animate({
        width: pageWidth * .8
      }, {duration: animationDuration, queue: false});

    return false;
  };

})(jQuery, Drupal);

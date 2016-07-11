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

    var pageWidth = $('#page-wrapper').width();

    var $page = $('#page');
    var $sidebar = $('<div />', {
      id: 'offcanvas',
      text: response.data,
      css: {
        'width': pageWidth * .2,
        'margin-right': -(pageWidth * .2)
      }
    });
    $('#page-wrapper').prepend($sidebar);
    $sidebar.animate({'margin-right': 0}, 1000);
    $page.animate({
      width: pageWidth * .8,
      float: 'left'
    }, 1000);

    // var $page
    // $('#page').addClass('page-reduced');
    // sidebar.addClass('showNav');
    return false;
  };

})(jQuery, Drupal);

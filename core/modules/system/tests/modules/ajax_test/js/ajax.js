/**
 * @file
 * Provides method to test ajax requests.
 */

(function ($, window, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.insertTest = {
    attach: function (context, settings) {
      $('.ajax-insert').once('ajax-insert').on('click', function (event) {
        event.preventDefault();
        var ajaxSettings = {
          url: event.currentTarget.getAttribute('href'),
          wrapper: 'ajax-target',
          base: false,
          element: false
        };
        var myAjaxObject = Drupal.ajax(ajaxSettings);
        myAjaxObject.execute();
      });
    }
  };
})(jQuery, window, Drupal, drupalSettings);

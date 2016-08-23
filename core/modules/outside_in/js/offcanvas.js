/**
 * @file
 * Drupal's off-canvas library.
 */

(function ($, Drupal, debounce, displace) {

  'use strict';

  function resetSize(event) {
    var offsets = displace.offsets;
    var $element = event.data.$element;
    var $widget = $element.dialog('widget');
    var edge = getEdge();

    var adjustedOptions = {
      position: {
        my: edge + ' top',
        at: edge + ' top' + (offsets.top !== 0 ? '+' + offsets.top : ''),
        of: window
      }
    };

    $widget.css({
      position: 'fixed',
      height: ($(window).height() - (offsets.top + offsets.bottom)) + 'px'
    });

    $element
      .dialog('option', adjustedOptions)
      .trigger('dialogContentResize.outsidein');
  }

  function handleDialogResize(event) {
    var $element = event.data.$element;
    var $widget = $element.dialog('widget');

    var $offsets = $widget.find('> :not(#drupal-offcanvas, .ui-resizable-handle)');
    var offset = 0;
    var modalHeight;

    // Let scroll element take all the height available.
    $element.css({overflow: 'visible', height: 'auto'});
    modalHeight = $widget.height();
    $offsets.each(function () { offset += $(this).outerHeight(); });

    // Take internal padding into account.
    var scrollOffset = $element.outerHeight() - $element.height();
    $element.height(modalHeight - offset - scrollOffset);
  }

  /**
   * Determine on which screen edge the tray should appear.
   *
   * @returns {string}
   *   'left' or 'right'
   */
  function getEdge() {
    return (document.documentElement.dir === 'rtl') ? 'left' : 'right';
  }

  function bodyPadding(event) {
    var edge = getEdge();
    var $element = event.data.$element;
    var $widget = $element.dialog('widget');
    var $body = $('body');

    var width = $widget.outerWidth();
    var bodyPadding = $body.css('padding-' + edge);
    if (width !== bodyPadding) {
      $body.css('padding-' + edge, width + 'px');
      $widget.attr('data-offset-' + edge, width);
      displace();
    }
  }


  $(window).on({
    'dialog:aftercreate': function (event, dialog, $element, settings) {
      if ($element.is('#drupal-offcanvas')) {
        var eventData = {settings: settings, $element: $element};

        $element
          .on('dialogresize.outsidein', eventData, debounce(bodyPadding, 100))
          .on('dialogContentResize.outsidein', eventData, handleDialogResize)
          .trigger('dialogresize.outsidein');

        $element.dialog('widget').attr('data-offset-' + getEdge(), '');

        $(window)
          .on('resize.outsidein scroll.outsidein', eventData, debounce(resetSize, 100))
          .trigger('resize.outsidein');

        //$(document).on('drupalViewportOffsetChange.outsidein', eventData, autoResize);
      }
    },
    'dialog:beforecreate': function (event, dialog, $element, settings) {
      if ($element.is('#drupal-offcanvas')) {
        var edge = getEdge();
        settings.position = {
          my: 'left top',
          at: edge + ' top',
          of: window
        };
        settings.dialogClass = 'ui-dialog-offcanvas';
      }
    },
    'dialog:beforeclose': function (event, dialog, $element) {
      $(document).off('.outsidein');
      $(window).off('.outsidein');
      $('body').css('padding-' + getEdge(), 0);
    }
  });

})(jQuery, Drupal, Drupal.debounce, Drupal.displace);

/**
 * @file
 * Dialog API inspired by HTML5 dialog element.
 *
 * @see http://www.whatwg.org/specs/web-apps/current-work/multipage/commands.html#the-dialog-element
 */

(function ($, Drupal, drupalSettings, debounce, displace) {

  'use strict';

  /**
   * Polyfill HTML5 dialog element with jQueryUI.
   *
   * @param {HTMLElement} element
   *   The element that holds the dialog.
   * @param {object} options
   *   jQuery UI options to be passed to the dialog.
   *
   * @return {Drupal.dialog~dialogDefinition}
   *   The dialog instance.
   */
  Drupal.offCanvasDialog = function (element, options) {
    var undef;
    var $element = $(element);

    // The minimum width to use body displace needs to match the width at which
    // the tray will be %100 width. @see outside_in.module.css
    var minDisplaceWidth = 768;

    /**
     * The edge of the screen that the dialog should appear on.
     *
     * @type {string}
     */
    var edge = document.documentElement.dir === 'rtl' ? 'left' : 'right';

    var $mainCanvasWrapper = $('[data-off-canvas-main-canvas]');
    var dialog = {
      open: false,
      returnValue: undef,
      show: function () {
        openDialog({modal: false});
      },
      showModal: function () {
        openDialog({modal: true});
      },
      close: closeDialog
    };

    function beforeCreate(settings) {
      $('body').addClass('js-tray-open');
      // @see http://api.jqueryui.com/position/
      settings.position = {
        my: 'left top',
        at: edge + ' top',
        of: window
      };
      settings.dialogClass += ' ui-dialog-off-canvas';
      // Applies initial height to dialog based on window height.
      // See http://api.jqueryui.com/dialog for all dialog options.
      settings.height = $(window).height();
      $element.attr('data-dialog-render', 'offCanvasDialog');
    }

    function afterCreate(settings) {
      var eventData = {settings: settings, $element: $element};
      $('.ui-dialog-off-canvas, .ui-dialog-off-canvas .ui-dialog-titlebar').toggleClass('ui-dialog-empty-title', !settings.title);

      $element
        .on('dialogresize.off-canvas', eventData, debounce(bodyPadding, 100))
        .on('dialogContentResize.off-canvas', eventData, handleDialogResize)
        .on('dialogContentResize.off-canvas', eventData, debounce(bodyPadding, 100))
        .trigger('dialogresize.off-canvas');

      $element.dialog('widget').attr('data-offset-' + edge, '');

      $(window)
        .on('resize.off-canvas scroll.off-canvas', eventData, debounce(resetSize, 100))
        .trigger('resize.off-canvas');
    }

    function openDialog(settings) {
      settings = $.extend({}, drupalSettings.dialog, options, settings);
      // Trigger a global event to allow scripts to bind events to the dialog.
      $(window).trigger('dialog:beforecreate', [dialog, $element, settings]);
      beforeCreate(settings);
      $element.dialog(settings);
      dialog.open = true;
      afterCreate(settings);
      $(window).trigger('dialog:aftercreate', [dialog, $element, settings]);
    }

    function beforeClose() {
      $('body').removeClass('js-tray-open');
      // Remove all *.off-canvas events
      $(document).off('.off-canvas');
      $(window).off('.off-canvas');
      $mainCanvasWrapper.css('padding-' + edge, 0);
    }

    function closeDialog(value) {
      beforeClose();
      $(window).trigger('dialog:beforeclose', [dialog, $element]);
      $element.dialog('close');
      dialog.returnValue = value;
      dialog.open = false;
      $(window).trigger('dialog:afterclose', [dialog, $element]);
    }

    /**
     * Resets the size of the dialog.
     *
     * @param {jQuery.Event} event
     *   The event triggered.
     */
    function resetSize(event) {
      var offsets = displace.offsets;
      var $element = event.data.$element;
      var $widget = $element.dialog('widget');

      var adjustedOptions = {
        // @see http://api.jqueryui.com/position/
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
          .trigger('dialogContentResize.off-canvas');
    }

    /**
     * Adjusts the dialog on resize.
     *
     * @param {jQuery.Event} event
     *   The event triggered.
     */
    function handleDialogResize(event) {
      var $element = event.data.$element;
      var $widget = $element.dialog('widget');

      var $offsets = $widget.find('> :not(#drupal-off-canvas, .ui-resizable-handle)');
      var offset = 0;
      var modalHeight;

      // Let scroll element take all the height available.
      $element.css({height: 'auto'});
      modalHeight = $widget.height();
      $offsets.each(function () { offset += $(this).outerHeight(); });

      // Take internal padding into account.
      var scrollOffset = $element.outerHeight() - $element.height();
      $element.height(modalHeight - offset - scrollOffset);
    }

    /**
     * Adjusts the body padding when the dialog is resized.
     *
     * @param {jQuery.Event} event
     *   The event triggered.
     */
    function bodyPadding(event) {
      if ($('body').outerWidth() < minDisplaceWidth) {
        return;
      }
      var $element = event.data.$element;
      var $widget = $element.dialog('widget');

      var width = $widget.outerWidth();
      var mainCanvasPadding = $mainCanvasWrapper.css('padding-' + edge);
      if (width !== mainCanvasPadding) {
        $mainCanvasWrapper.css('padding-' + edge, width + 'px');
        $widget.attr('data-offset-' + edge, width);
        displace();
      }
    }

    return dialog;
  };

})(jQuery, Drupal, drupalSettings, Drupal.debounce, Drupal.displace);

/**
 * @file
 * Drupal's off-canvas library.
 *
 * @todo This functionality should extracted into a new core library or a part
 *  of the current drupal.dialog.ajax library.
 *  https://www.drupal.org/node/2784443
 */

(function ($, Drupal, debounce, displace) {

  'use strict';

  /**
   * Resets the size of the dialog.
   *
   * @param {jQuery.Event} event
   *   The event triggered.
   */
  function resetSize(event) {
    var offCanvasDialog = event.data.offCanvasDialog;
    var offsets = displace.offsets;
    var $element = event.data.$element;
    var container = offCanvasDialog.getContainer($element);

    var adjustedOptions = {
      // @see http://api.jqueryui.com/position/
      position: {
        my: offCanvasDialog.getEdge() + ' top',
        at: offCanvasDialog.getEdge() + ' top' + (offsets.top !== 0 ? '+' + offsets.top : ''),
        of: window
      }
    };

    container.css({
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
    var offCanvasDialog = event.data.offCanvasDialog;
    var $container = offCanvasDialog.getContainer($element);

    var $offsets = $container.find('> :not(#drupal-off-canvas, .ui-resizable-handle)');
    var offset = 0;
    var modalHeight;

    // Let scroll element take all the height available.
    $element.css({height: 'auto'});
    modalHeight = $container.height();
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
    var offCanvasDialog = event.data.offCanvasDialog;
    if ($('body').outerWidth() < offCanvasDialog.minDisplaceWidth) {
      return;
    }
    var $element = event.data.$element;
    var $container = offCanvasDialog.getContainer($element);
    var $mainCanvasWrapper = offCanvasDialog.$mainCanvasWrapper;

    var width = $container.outerWidth();
    var mainCanvasPadding = $mainCanvasWrapper.css('padding-' + offCanvasDialog.getEdge());
    if (width !== mainCanvasPadding) {
      $mainCanvasWrapper.css('padding-' + offCanvasDialog.getEdge(), width + 'px');
      $container.attr('data-offset-' + offCanvasDialog.getEdge(), width);
      displace();
    }
  }

  /**
   * Attaches off-canvas dialog behaviors.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches event listeners for off-canvas dialogs.
   */

  Drupal.offCanvas = {
    // The minimum width to use body displace needs to match the width at which
    // the tray will be %100 width. @see outside_in.module.css
    minDisplaceWidth: 768,

    $mainCanvasWrapper: $('[data-off-canvas-main-canvas]'),
    /**
     * Determines if a
     * @return {bool}
     */
    isOffCanvas: function (dialog, $element, settings) {
      return $element.is('#drupal-off-canvas');
    },
    open: function (event, dialog, $element, settings) {
      $('body').addClass('js-tray-open');
      settings.dialogClass += ' ui-dialog-off-canvas';
      // @see http://api.jqueryui.com/position/
      settings.position = {
        my: 'left top',
        at: this.getEdge() + ' top',
        of: window
      };
      // Applies initial height to dialog based on window height.
      // See http://api.jqueryui.com/dialog for all dialog options.
      settings.height = $(window).height();
    },
    close: function (event, dialog, $element) {
      $('body').removeClass('js-tray-open');
      // Remove all *.off-canvas events
      $(document).off('.off-canvas');
      $(window).off('.off-canvas');
      this.$mainCanvasWrapper.css('padding-' + this.getEdge(), 0);
    },
    render: function (event, dialog, $element, settings) {
      var eventData = {settings: settings, $element: $element, offCanvasDialog: this};
      $('.ui-dialog-off-canvas, .ui-dialog-off-canvas .ui-dialog-titlebar').toggleClass('ui-dialog-empty-title', !settings.title);

      $element
          .on('dialogresize.off-canvas', eventData, debounce(bodyPadding, 100))
          .on('dialogContentResize.off-canvas', eventData, handleDialogResize)
          .on('dialogContentResize.off-canvas', eventData, debounce(bodyPadding, 100))
          .trigger('dialogresize.off-canvas');

      this.getContainer($element).attr('data-offset-' + this.getEdge(), '');

      $(window)
          .on('resize.off-canvas scroll.off-canvas', eventData, debounce(resetSize, 100))
          .trigger('resize.off-canvas');
    },
    handleDialogResize: handleDialogResize,
    resetSize: resetSize,
    bodyPadding: bodyPadding,
    getContainer: function ($element) {
      return $element.dialog('widget');
    },
    /**
     * The edge of the screen that the dialog should appear on.
     *
     * @return {string}
     */
    getEdge: function () {
      return document.documentElement.dir === 'rtl' ? 'left' : 'right';
    }
  };

  Drupal.behaviors.offCanvasEvents = {
    attach: function () {
      $(window).once('off-canvas').on({
        'dialog:beforecreate': function (event, dialog, $element, settings) {
          if (Drupal.offCanvas.isOffCanvas(dialog, $element, settings)) {
            Drupal.offCanvas.open(event, dialog, $element, settings)
          }
        },
        'dialog:aftercreate': function (event, dialog, $element, settings) {
          if (Drupal.offCanvas.isOffCanvas(dialog, $element, settings)) {
            Drupal.offCanvas.render(event, dialog, $element, settings);
          }
        },
        'dialog:beforeclose': function (event, dialog, $element) {
          if (Drupal.offCanvas.render(event, dialog, $element, settings)) {
            Drupal.offCanvas.close(event, dialog, $element)
          }
        }
      });
    }
  };

})(jQuery, Drupal, Drupal.debounce, Drupal.displace);

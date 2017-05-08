/**
 * @file
 * Dialog API inspired by HTML5 dialog element.
 *
 * @see http://www.whatwg.org/specs/web-apps/current-work/multipage/commands.html#the-dialog-element
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Default dialog options.
   *
   * @type {object}
   *
   * @prop {bool} [autoOpen=true]
   * @prop {string} [dialogClass='']
   * @prop {string} [buttonClass='button']
   * @prop {string} [buttonPrimaryClass='button--primary']
   * @prop {function} close
   */
  drupalSettings.dialog = {
    autoOpen: true,
    dialogClass: '',
    // Drupal-specific extensions: see dialog.jquery-ui.js.
    buttonClass: 'button',
    buttonPrimaryClass: 'button--primary',
    // When using this API directly (when generating dialogs on the client
    // side), you may want to override this method and do
    // `jQuery(event.target).remove()` as well, to remove the dialog on
    // closing.
    close: function (event) {
      Drupal.dialog(event.target).close();
      Drupal.detachBehaviors(event.target, null, 'unload');
    }
  };

  /**
   * @typedef {object} Drupal.dialog~dialogDefinition
   *
   * @prop {boolean} open
   *   Is the dialog open or not.
   * @prop {*} returnValue
   *   Return value of the dialog.
   * @prop {function} show
   *   Method to display the dialog on the page.
   * @prop {function} showModal
   *   Method to display the dialog as a modal on the page.
   * @prop {function} close
   *   Method to hide the dialog from the page.
   */

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
  Drupal.dialog = function (element, options) {
    var undef;
    var $element = $(element);
    var dialog = {
      open: false,
      returnValue: undef,
      show: function () {
        openDialog({modal: false});
      },
      showModal: function () {
        openDialog({modal: true});
      },
      close: closeDialog,
      getContainer: getContainer,
      setOptions: setOptions,
      handleDialogResize: handleDialogResize
    };

    function openDialog(settings) {
      settings = $.extend({}, drupalSettings.dialog, options, settings);
      // Trigger a global event to allow scripts to bind events to the dialog.
      $(window).trigger('dialog:beforecreate', [dialog, $element, settings]);
      $element.dialog(settings);
      dialog.open = true;
      $(window).trigger('dialog:aftercreate', [dialog, $element, settings]);
    }

    function closeDialog(value) {
      $(window).trigger('dialog:beforeclose', [dialog, $element]);
      $element.dialog('close');
      dialog.returnValue = value;
      dialog.open = false;
      $(window).trigger('dialog:afterclose', [dialog, $element]);
    }

    /**
     * Gets the HTMLElement that contains the dialog.
     *
     * jQuery UI dialogs are contained in outer HTMLElement.
     * For themes or modules that override the Drupal.dialog with non jQuery UI
     * dialogs that do not have a containing element other than the dialog
     * itself should just return the dialog element.
     *
     * @return {HTMLElement} element
     *   The HTMLElement that contains the dialog.
     */
    function getContainer() {
      return $element.dialog('widget')[0];
    }

    /**
     * Sets the options for the dialog.
     *
     * @param {object} options
     *   jQuery UI options to be passed to the dialog.
     */
    function setOptions(options) {
      $element.dialog('option', options);
    }

    /**
     * Adjusts the dialog on resize.
     *
     * @param {jQuery.Event} event
     *   The event triggered.
     */
    function handleDialogResize(event) {
      var $element = event.data.$element;
      var $container = $(event.data.dialog.getContainer());

      var $offsets = $container.find('> :not(#' + $element.attr('id') + ', .ui-resizable-handle)');
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

    return dialog;
  };

})(jQuery, Drupal, drupalSettings);

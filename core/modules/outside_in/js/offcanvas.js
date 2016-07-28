/**
 * @file
 * Drupal's off canvas library.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Create a wrapper container for the off canvas element.
   * @param  {number} pageWidth
   *   The width of #page-wrapper.
   * @return {object}
   *   jQuery object that is the off canvas wrapper element.
   */
  var createOffCanvasWrapper = function (pageWidth) {
   return $('<div />', {
     'id': 'offcanvas',
     'role': 'region',
     'aria-labelledby': 'offcanvas-header'
   });
 };

  /**
   * Create the title element for the off canvas element.
   * @param  {string} title
   *   The title string.
   * @return {object}
   *   jQuery object that is the off canvas title element.
   */
  var createTitle = function (title) {
    return $('<h1 />', {text: title, id: 'offcanvas-header'});
  };

  /**
   * Create the actual off canvas content.
   * @param  {string} data
   *   This is fully rendered html from Drupal.
   * @return {object}
   *   jQuery object that is the off canvas content element.
   */
  var createOffCanvasContent = function (data) {
    return $('<div />', {class: 'offcanvas-content', html: data});
  };

  /**
   * Create the off canvas close element.
   * @param  {object} offCanvasWrapper
   *   The jQuery off canvas wrapper element
   * @param  {object} pageWrapper
   *   The jQuery off page wrapper element
   * @return {object}
   *   jQuery object that is the off canvas close element.
   */
  var createOffCanvasClose = function (offCanvasWrapper, pageWrapper) {
    return $('<button />', {
      'class': 'offcanvasClose',
      'aria-label': Drupal.t('Close configuration tray.'),
      'html': '<span class="visually-hidden">' + Drupal.t('Close') + '</span>'
    }).click(function () {
      pageWrapper
        .removeClass('js-tray-open')
        .one('webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend', function (e) {
          Drupal.offCanvas.visible = false;
          offCanvasWrapper.remove();
          Drupal.announce(Drupal.t('Configuration tray closed.'));
        }
      );
    });
  };


  /**
   * Command to open an off canvas element.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.openOffCanvas = function (ajax, response, status) {
    // Discover display/viewport size.
    // @todo Work in breakpoints for tray size.
    var $pageWrapper = $('#main-canvas-wrapper');
    var pageWidth = $pageWrapper.width();

    // Set the initial state of the off canvas element.
    // If the state has been set previously, use it.
    Drupal.offCanvas = {
      visible: (Drupal.offCanvas ? Drupal.offCanvas.visible : false)
    };

    // Construct off canvas wrapper
    var $offcanvasWrapper = createOffCanvasWrapper(pageWidth);

    // Construct off canvas internal elements.
    var $offcanvasClose = createOffCanvasClose($offcanvasWrapper, $pageWrapper);
    var $title = createTitle(response.dialogOptions.title);
    var $offcanvasContent = createOffCanvasContent(response.data);

    // Put everything together.
    $offcanvasWrapper.append([$offcanvasClose, $title, $offcanvasContent]);

    // Handle opening or updating tray with content.
    var existingTray = false;
    if (Drupal.offCanvas.visible) {
      // Remove previous content then append new content.
      $pageWrapper.find('#offcanvas').remove();
      existingTray = true;
    }
    $pageWrapper.addClass('js-tray-open');
    Drupal.offCanvas.visible = true;
    $pageWrapper.append($offcanvasWrapper);
    if (existingTray) {
      Drupal.announce(Drupal.t('Configuration tray content has been updated.'));
    }
    else {
      Drupal.announce(Drupal.t('Configuration tray opened.'));
    }
    Drupal.attachBehaviors(document.querySelector('#offcanvas'),drupalSettings);
  };

})(jQuery, Drupal);

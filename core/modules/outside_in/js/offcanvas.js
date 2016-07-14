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
      id: 'offcanvas',
      css: {
        width: pageWidth * .2,
        right: -(pageWidth * .2)
      }
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
    return $('<h1 />', {text: title});
  };

  /**
   * Create the actual off canvas content.
   * @param  {string} data
   *   This is fully rendered html from Drupal.
   * @return {object}
   *   jQuery object that is the off canvas content element.
   */
  var createOffCanvasContent = function (data) {
    return $('<div />', {class: 'content', html: data});
  };

  /**
   * Create the off canvas close element.
   * @param  {object} offCanvasWrapper
   *   The jQuery off canvas wrapper element
   * @param  {object} page
   *   The #page element.
   * @param  {number} pageWidth
   *   The width of #page-wrapper
   * @param  {number} animationDuration
   *   The duration of the animation.
   * @return {object}
   *   jQuery object that is the off canvas close element.
   */
  var createOffCanvasClose = function (offCanvasWrapper, page, pageWidth, animationDuration) {
    return $('<span />', {class: 'offcanvasClose', text: 'x'}).click(function () {
      offCanvasWrapper.animate({right: -(pageWidth * .2)}, {duration: animationDuration, queue: false});
      page
        .animate({width: pageWidth}, {duration: animationDuration, queue: false, complete: function () {
          // Remove some leftovers on $page.
          page
            .removeClass('offCanvasDisplayed')
            .removeAttr('style');

          // Remove off canvas element, and set display state variable.
          Drupal.offCanvas.visible = false;
          offCanvasWrapper.remove();
        }});
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
   *
   * @return {bool}
   *   Returns false.
   */
  Drupal.AjaxCommands.prototype.openOffCanvas = function (ajax, response, status) {
    // Set animation duration and get #page-wrapper width.
    var animationDuration = 600;
    var $pageWrapper = $('#page-wrapper');
    var pageWidth = $pageWrapper.width();

    var $page = $('#page');

    // Set the initial state of the off canvas element.
    // If the state has been set previously, use it.
    Drupal.offCanvas = {
      visible: Drupal.offCanvas ? Drupal.offCanvas.visible : false
    };

    // Construct off canvas wrapper
    var $offcanvasWrapper = createOffCanvasWrapper(pageWidth);

    // Construct off canvas internal elements.
    var $offcanvasClose = createOffCanvasClose($offcanvasWrapper, $page, pageWidth, animationDuration);
    var $title = createTitle('My Title');
    var $offcanvasContent = createOffCanvasContent(response.data);

    // Put everything together.
    $offcanvasWrapper.append([$offcanvasClose, $title, $offcanvasContent]);

    // Only add off canvas elements if we have none visible.
    if (!Drupal.offCanvas.visible) {
      // Append off canvas wrapper to the 'page'
      $pageWrapper.append($offcanvasWrapper);

      // Animate $page and $offcanvasWrapper to simulate a slide in effect
      $page
        .animate({
          width: pageWidth * .8
        }, {
          duration: animationDuration,
          queue: false,
          start: function () {
            $page.addClass('offCanvasDisplayInProgress');
          },
          complete: function () {
            $page
              .removeClass('offCanvasDisplayInProgress')
              .addClass('offCanvasDisplayed');
          }
        });
      $offcanvasWrapper
        .animate({right: 0}, {
          duration: animationDuration,
          queue: false,
          start: function () {
            // Set the offCanvas visible state.
            Drupal.offCanvas.visible = true;
          }
        });
    }

    return false;
  };

})(jQuery, Drupal);

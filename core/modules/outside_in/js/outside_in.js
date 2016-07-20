/**
 * @file
 * Drupal's Outside In library.
 */

(function ($, Drupal) {
  'use strict';

  // Bind a listener to the 'edit' button
  // Toggle the js-outside-edit-mode class on items that we want
  // to disable while in edit mode.
  $('div.contextual-toolbar-tab.toolbar-tab button').click(function (e) {
    $('.outside-in-editable a, .outside-in-editable button')
      .not('div.contextual a, div.contextual button')
      .toggleClass('js-outsidein-edit-mode');
    $('.outside-in-editable').toggleClass('focus');
  });

  // Bind an event listener to the .outside-in-editable div
  // This listen for click events and stops default actions of those elements.
  $('.outside-in-editable').on('click', '.js-outsidein-edit-mode', function (e) {
    if (localStorage.getItem('Drupal.contextualToolbar.isViewing') === 'false') {
      e.preventDefault();
    }
  });

  // Bind an event listener to the .outside-in-editable div
  // When a click occurs try and find the outside-in edit link
  // and click it.
  $('.outside-in-editable')
    .not('div.contextual a, div.contextual button')
    .click(function (e) {
      if ($(e.target.offsetParent).hasClass('contextual')) {
        return;
      }
      var editLink = $(e.target).find('li.outside-inblock-configure a')[0];

      (editLink ? editLink : $(e.target).parents('.outside-in-editable')
        .find('li.outside-inblock-configure a')[0])
        .click();
    });

  /**
   * Add Ajax behaviours to links added by contextual links
   *
   * @todo Fix contextual links to work with use-ajax links.
   *   @see https://www.drupal.org/node/2764931
   *
   * @param {jQuery.Event} event
   *   The `drupalContextualLinkAdded` event.
   * @param {object} data
   *   An object containing the data relevant to the event.
   *
   * @listens event:drupalContextualLinkAdded
   */
  $(document).on('drupalContextualLinkAdded', function (event, data) {
    // Bind Ajax behaviors to all items showing the class.
    data.$el.find('.use-ajax').once('ajax').each(function () {
      // Below is copied directly from ajax.js to keep behavior the same.
      var element_settings = {};
      // Clicked links look better with the throbber than the progress bar.
      element_settings.progress = {type: 'throbber'};

      // For anchor tags, these will go to the target of the anchor rather
      // than the usual location.
      var href = $(this).attr('href');
      if (href) {
        element_settings.url = href;
        element_settings.event = 'click';
      }
      element_settings.dialogType = $(this).data('dialog-type');
      element_settings.dialog = $(this).data('dialog-options');
      element_settings.base = $(this).attr('id');
      element_settings.element = this;
      Drupal.ajax(element_settings);
    });
  });

  /**
   * Attaches contextual's edit toolbar tab behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches contextual toolbar behavior on a contextualToolbar-init event.
   */
  Drupal.behaviors.outsideinedit = {
    attach: function (context) {
      var editMode = localStorage.getItem('Drupal.contextualToolbar.isViewing') === 'false';
      if (editMode) {
        $('.outside-in-editable').addClass('focus');
        var itemsToDisable = $('.outside-in-editable a, .outside-in-editable button')
          .not('div.contextual a, div.contextual button');

        itemsToDisable
          .addClass('js-outsidein-edit-mode');
      }
    }
  };

})(jQuery, Drupal);

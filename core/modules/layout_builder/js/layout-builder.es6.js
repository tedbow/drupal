/**
 * @file
 * Attaches the behaviors for the Layout Builder module.
 */

(($, Drupal) => {
  const { ajax, behaviors, announce } = Drupal;

  /**
   * Provides the ability to drag blocks to new positions in the layout.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach block drag behavior to the Layout Builder UI.
   */
  behaviors.layoutBuilderBlockDrag = {
    attach(context) {
      $(context)
        .find('.layout-builder--layout__region')
        .sortable({
          items: '> .draggable',
          connectWith: '.layout-builder--layout__region',
          placeholder: 'ui-state-drop',

          /**
           * Updates the layout with the new position of the block.
           *
           * @param {jQuery.Event} event
           *   The jQuery Event object.
           * @param {Object} ui
           *   An object containing information about the item being sorted.
           */
          update(event, ui) {
            // Check if the region from the event and region for the item match.
            const itemRegion = ui.item.closest(
              '.layout-builder--layout__region',
            );
            if (event.target === itemRegion[0]) {
              // Find the destination delta.
              const deltaTo = ui.item
                .closest('[data-layout-delta]')
                .data('layout-delta');
              // If the block didn't leave the original delta use the destination.
              const deltaFrom = ui.sender
                ? ui.sender.closest('[data-layout-delta]').data('layout-delta')
                : deltaTo;
              ajax({
                url: [
                  ui.item
                    .closest('[data-layout-update-url]')
                    .data('layout-update-url'),
                  deltaFrom,
                  deltaTo,
                  itemRegion.data('region'),
                  ui.item.data('layout-block-uuid'),
                  ui.item
                    .prev('[data-layout-block-uuid]')
                    .data('layout-block-uuid'),
                ]
                  .filter(element => element !== undefined)
                  .join('/'),
              }).execute();
            }
          },
        });
    },
  };

  /**
   * Toggles the Layout Builder UI between live preview and grid modes.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach live preview toggle to the Layout Builder UI.
   */
  behaviors.layoutBuilderToggleLivePreview = {
    attach(context) {
      const $layoutBuilder = $('#layout-builder');
      // The live preview toggle.
      const $layoutBuilderLivePreview = $('#layout-builder-live-preview');
      // data-live-preview-id specifies the layout being edited.
      const livePreviewId = $layoutBuilderLivePreview.data('live-preview-id');
      // Bool tracking if live preview is enabled.
      const livePreviewActive = localStorage.getItem(livePreviewId);

      /**
       * Switches the Layout Builder UI to grid mode.
       *
       * Grid mode hides block preview content. It is replaced with the value
       * of the block's data-layout-grid-mode-label attribute.
       *
       * @param transitionTime
       *  Time in milliseconds that the elements transition to hidden.
       */
      function gridMode(transitionTime) {
        $layoutBuilder.addClass('layout-builder-grid-mode');
        $('[data-layout-grid-mode-label]').each((i, element) => {
          const $element = $(element);
          $element.children(':not(.contextual)').hide(transitionTime);
          const adminLabel = document.createElement('h2');
          adminLabel.className = 'data-layout-grid-mode-label';
          adminLabel.innerHTML = $element.attr('data-layout-grid-mode-label');
          $element.prepend(adminLabel);
        });
      }
      /**
       * Switches the Layout Builder UI to live preview mode.
       *
       * Live preview mode is the default Layout Builder editor experience.
       * When this mode is switched to from grid mode, the grid mode labels are
       * removed, and hidden block content is made visible.
       *
       * @param transitionTime
       *  Time in milliseconds that the elements transition to visible.
       */
      function livePreviewMode(transitionTime) {
        $layoutBuilder.removeClass('layout-builder-grid-mode');
        $('.data-layout-grid-mode-label').remove();
        $('[data-layout-grid-mode-label]').each((i, element) => {
          $(element)
            .children()
            .show(transitionTime);
        });
      }

      $('#layout-builder-live-preview', context).on('change', event => {
        const $target = $(event.currentTarget);
        const isChecked = $target.is(':checked');
        localStorage.setItem(livePreviewId, isChecked);

        if (isChecked) {
          livePreviewMode(500);
          announce(Drupal.t('Layout Builder editor is in live preview mode.'));
        } else {
          gridMode(500);
          announce(Drupal.t('Layout Builder editor is in grid mode.'));
        }
      });

      /**
       * On every rebuild, see if live preview is disabled. If yes, switch to
       * grid mode.
       */
      if (livePreviewActive === 'false') {
        $layoutBuilderLivePreview.attr('checked', false);
        gridMode(0);
      }
    },
  };
})(jQuery, Drupal);

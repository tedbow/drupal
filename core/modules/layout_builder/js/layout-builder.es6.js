(($, { ajax, behaviors }) => {
  behaviors.layoutBuilder = {
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
  // Highlight the element that just triggered the layout builder dialog.
  $(window).on('dialog:aftercreate', (event, dialog, $element) => {
    // Remove the highlight form the previous element if any.
    $('.layout-builder-highlight').removeClass('layout-builder-highlight');
    const id = $element
      .find('[data-layout-builder-target-highlight-id]')
      .attr('data-layout-builder-target-highlight-id');
    if (id) {
      $(`[data-layout-builder-highlight-id="${id}"]`).addClass(
        'layout-builder-highlight',
      );
    }
  });
  $(window).on('dialog:afterclose', () => {
    $('.layout-builder-highlight').removeClass('layout-builder-highlight');
  });
})(jQuery, Drupal);

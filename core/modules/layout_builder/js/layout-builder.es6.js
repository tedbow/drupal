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
      $(context)
        .find('[data-layout-builder-reorder] [data-region_to]')
        .on('click', e => {
          const delta = $(e.target).data('delta_to');
          const region = $(e.target).data('region_to');
          const precedingUuid = $(e.target).data('preceding_block_uuid');

          const block = $(e.target).closest('[data-layout-block-uuid]');
          if (precedingUuid) {
            const preceedingBlock = $(
              `#layout-builder [data-layout-block-uuid="${precedingUuid}"]`,
            );
            preceedingBlock.after(block);
          } else {
            const regionElement = $(
              `#layout-builder [data-layout-delta="${delta}"] [data-region="${region}"]`,
            );
            const firstBlock = regionElement
              .children('[data-layout-block-uuid], .new-block')
              .first();
            firstBlock.before(block);
          }
          $(e.target).focus();
          ajax({
            progress: { type: 'fullscreen' },
            url: $(e.target).attr('href'),
          }).execute();
          e.preventDefault();
        });
    },
  };
})(jQuery, Drupal);

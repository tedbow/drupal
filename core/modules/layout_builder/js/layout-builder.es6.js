(($, { ajax, behaviors, debounce }) => {
  behaviors.layoutBuilderBlockFilter = {
    attach(context) {
      const $input = $('input.js-layout-builder-filter', context).once('block-filter-text');
      const $categories = $('.js-layout-builder-categories', context);
      let $filterLinks;

      /**
       * Filters the block list.
       *
       * @param {jQuery.Event} e
       *   The jQuery event for the keyup event that triggered the filter.
       */
      function filterBlockList(e) {
        const query = $(e.target)
          .val()
          .toLowerCase();

        /**
         * Shows or hides the block entry based on the query.
         *
         * @param {number} index
         *   The index in the loop, as provided by `jQuery.each`
         * @param {HTMLElement} link
         *   The link to add the block.
         */
        function toggleBlockEntry(index, link) {
          const $link = $(link);
          const textMatch =
            $link
              .text()
              .toLowerCase()
              .indexOf(query) !== -1;
          $link.toggle(textMatch);
        }

        $categories.find('.js-layout-builder-category').show();
        $filterLinks.show();

        // Filter if the length of the query is at least 2 characters.
        if (query.length >= 2) {
          $categories.find('.js-layout-builder-category').attr('open', '');
          $filterLinks.each(toggleBlockEntry);
          $categories.find('.js-layout-builder-category').each(function() {
            const hasBlocks = $(this).find('.js-layout-builder-block-link:visible').length > 0;
            $(this).toggle(hasBlocks);
          });
          Drupal.announce(
            Drupal.formatPlural(
              $categories.find('.js-layout-builder-block-link:visible').length,
              '1 block is available in the modified list.',
              '@count blocks are available in the modified list.',
            ),
          );
        }
      }

      if ($input.length) {
        $filterLinks = $categories.find('.js-layout-builder-block-link');
        $input.on('keyup', debounce(filterBlockList, 200));
      }
    }
  };
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
})(jQuery, Drupal);

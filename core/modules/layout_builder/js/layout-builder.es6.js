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
      $('.layout-builder-block-reorder-previous a').once().on('click.layoutbuilder', e => {
        function setContextualLinkFocus(link) {
          const button = $(link).closest('[data-contextual-id]').find('button.trigger');
          $(link).trigger('blur');
          button.trigger('focus');
          const returnEvent = jQuery.Event("keydown");
          returnEvent.which = 13; // # Some key code value
          button.trigger(returnEvent);
          $(link).closest('[data-contextual-id]').find('.contextual-links').style('display', 'block');
          const tabEvent = jQuery.Event("keydown");
          tabEvent.which = 9; // # Some key code value
          $(document.activeElement).trigger(tabEvent);
          $(document.activeElement).trigger(tabEvent);
          $(document.activeElement).trigger(tabEvent);
          //button.trigger('click');
          //button.click();
        }
        const current = $(e.target).closest('.draggable');
        //current.classList.add('drag-current');
        const prev = current.prev();
        prev.before(current);
        e.preventDefault();
        e.stopPropagation();
        setContextualLinkFocus(e.target)
        return;
        const newNode = document.createElement("span");
        newNode.classList.add('ui-state-drop');

        current.parentNode.insertBefore(newNode, current);
        //current.prev().before(current);
        //$('.layout-builder--layout__region').sortable( "refresh" );
        //current.sortable( "refreshPositions" );
        //alert('jdf');


      });
    },
  };
})(jQuery, Drupal);

(($, { ajax, behaviors }) => {
  /**
   * Gets the layout delta for a element.
   *
   * @param element
   * @returns {*}
   */
  function getLayoutDelta(element) {
    return element.closest('[data-layout-delta]').data('layout-delta');
  }

  /**
   * Update the component position.
   *
   * @param item
   * @param deltaFrom
   * @param directionFocus
   */
  function updateComponentPosition(item, deltaFrom) {
    const itemRegion = item.closest('.layout-builder--layout__region');
    // Find the destination delta.
    const deltaTo = getLayoutDelta(item);
    ajax({
      progress: { type: 'fullscreen' },
      url: [
        item.closest('[data-layout-update-url]').data('layout-update-url'),
        deltaFrom,
        deltaTo,
        itemRegion.data('region'),
        item.data('layout-block-uuid'),
        item.prev('[data-layout-block-uuid]').data('layout-block-uuid'),
      ]
        .filter(element => element !== undefined)
        .join('/'),
    }).execute();
  }

  function watchActionLinks($elements) {
    $elements.find('a[data-layout-builder-action]').on('click', e => {
      const action = $(e.target).attr('data-layout-builder-action');
      $('#layout-builder').attr('data-layout-builder-current-action', action);
    });
  }

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
              const deltaTo = getLayoutDelta(ui.item);
              // If the block didn't leave the original delta use the destination.
              const deltaFrom = ui.sender ? getLayoutDelta(ui.sender) : deltaTo;
              updateComponentPosition(ui.item, deltaFrom);
            }
          },
        });
      $(context)
        .find('.block-destination')
        .on('click', e => {
          const movingBlock = $('[data-layout-builder-moving-block]');
          const deltaFrom = movingBlock
            .closest('[data-layout-delta]')
            .data('layout-delta');
          $(e.target)
            .closest('.block-destination')
            .replaceWith(movingBlock);
          movingBlock.removeClass('layout-builder-moving-block');
          updateComponentPosition(movingBlock, deltaFrom);
          e.preventDefault();
        });
      watchActionLinks($(context));
    },
  };
  /**
   * Reacts to contextual links being added.
   *
   * @param {jQuery.Event} event
   *   The `drupalContextualLinkAdded` event.
   * @param {object} data
   *   An object containing the data relevant to the event.
   *
   * @listens event:drupalContextualLinkAdded
   */
  $(document).on('drupalContextualLinkAdded', (event, data) => {
    watchActionLinks(data.$el);
    /**
     * Bind a listener to all 'Move block' links for blocks.
     */
    data.$el
      .find('.layout-builder-move-block a')
      .on('click.layoutbuilder', e => {
        $('#drupal-off-canvas').dialog('close');
        $('[data-layout-builder-moving-block]')
          .removeAttr('data-layout-builder-moving-block')
          .removeClass('layout-builder-moving-block');
        $('.layout-builder-invalid-destination').removeClass(
          'layout-builder-invalid-destination',
        );
        const movingBlock = $(e.target).closest('[data-layout-block-uuid]');
        movingBlock.attr('data-layout-builder-moving-block', true);
        movingBlock.addClass('layout-builder-moving-block');
        $(
          `.block-destination[data-preceding-block-uuid="${movingBlock.attr(
            'data-layout-block-uuid',
          )}"]`,
        ).addClass('layout-builder-invalid-destination');

        if (movingBlock.prev('[data-layout-block-uuid]').length === 0) {
          movingBlock
            .prev('.block-destination')
            .addClass('layout-builder-invalid-destination');
        }
        if (movingBlock.next('[data-layout-block-uuid]').length === 0) {
          movingBlock
            .next('.block-destination')
            .addClass('layout-builder-invalid-destination');
        }

        e.preventDefault();
      });
  });
})(jQuery, Drupal);

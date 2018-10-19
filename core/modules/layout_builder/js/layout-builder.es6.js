(($, { ajax, behaviors }) => {
  /**
   * Gets the region for a component.
   *
   * @param {jQuery} component
   *   The component.
   * @return {jQuery}
   *   The components region element.
   */
  function getComponentRegion(component) {
    return component.closest('[data-region]');
  }

  /**
   * Gets the section of element.
   *
   * @param element
   * @returns {*}
   */
  function getSection(element) {
    return element.closest('.layout-section');
  }

  /**
   * Gets the layout delta for a component.
   *
   * @param element
   * @returns {*}
   */
  function getComponentLayoutDelta(element) {
    return element.closest('[data-layout-delta]').data('layout-delta');
  }

  /**
   * Gets the next matching sibling.
   *
   * @param {jQuery} element
   *   The element to get the sibling for.
   * @param direction
   * @param selector
   * @return {jQuery}
   *   The sibling that match the selector.
   */
  function getSiblingByDirection(element, direction, selector) {
    if (direction === 'previous') {
      return element.prevAll(selector).first();
    }
    return element.nextAll(selector).first();
  }

  /**
   * Finds the region to move to.
   *
   * @param element
   * @param direction
   * @returns {*}
   */
  function findMoveToRegion(element, direction) {
    const elementRegion = getComponentRegion(element);
    const moveToRegion = getSiblingByDirection(
      elementRegion,
      direction,
      '[data-region]',
    );
    // If there is not a sibling region find sibling section.
    if (moveToRegion.length === 0) {
      const componentSection = getSection(elementRegion);
      const moveToSection = getSiblingByDirection(
        componentSection,
        direction,
        '.layout-section',
      );
      if (moveToSection.length === 0) {
        return null;
      }
      const sectionRegions = moveToSection.find('[data-region]');
      return direction === 'previous'
        ? sectionRegions.last()
        : sectionRegions.first();
    }
    return moveToRegion;
  }

  /**
   * Finds the elements the element the current element should move to.
   *
   * @param region
   * @param element
   * @param direction
   * @returns {null}
   */
  function findRegionMoveToElement(region, element, direction) {
    let moveToElement;
    if (region[0] === getComponentRegion(element)[0]) {
      if (direction === 'previous') {
        moveToElement = element.prev('[data-layout-block-uuid]');
      } else {
        moveToElement = element
          .next('[data-layout-block-uuid], .new-block')
          .next('[data-layout-block-uuid], .new-block');
      }
      if (moveToElement.length === 0) {
        const moveToRegion = findMoveToRegion(element, direction);
        if (moveToRegion) {
          return findRegionMoveToElement(moveToRegion, element, direction);
        }
        return null;
      }
    } else if (direction === 'next') {
      moveToElement = region
        .children('[data-layout-block-uuid], .new-block')
        .first();
    } else {
      moveToElement = region.find('.new-block');
    }

    return moveToElement.length === 0 ? null : moveToElement;
  }

  /**
   * Update the component position.
   *
   * @param item
   * @param deltaFrom
   * @param directionFocus
   */
  function updateComponentPosition(item, deltaFrom, directionFocus = 'none') {
    const itemRegion = item.closest('.layout-builder--layout__region');
    // Find the destination delta.
    const deltaTo = getComponentLayoutDelta(item);
    ajax({
      progress: { type: 'fullscreen' },
      url: [
        item.closest('[data-layout-update-url]').data('layout-update-url'),
        deltaFrom,
        deltaTo,
        itemRegion.data('region'),
        item.data('layout-block-uuid'),
        directionFocus,
        item.prev('[data-layout-block-uuid]').data('layout-block-uuid'),
      ]
        .filter(element => element !== undefined)
        .join('/'),
    }).execute();
  }

  /**
   * Move the component in a direction.
   *
   * @param element
   * @param direction
   */
  function moveComponent(element, direction) {
    const moveToElement = findRegionMoveToElement(
      getComponentRegion(element),
      element,
      direction,
    );
    if (moveToElement) {
      element.addClass('updating');
      const deltaFrom = getComponentLayoutDelta(element);
      moveToElement.before(element);
      updateComponentPosition(element, deltaFrom, direction);
    }
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
              const deltaTo = getComponentLayoutDelta(ui.item);
              // If the block didn't leave the original delta use the destination.
              const deltaFrom = ui.sender
                ? getComponentLayoutDelta(ui.sender)
                : deltaTo;
              updateComponentPosition(ui.item, deltaFrom);
            }
          },
        });
      $(context)
        .find(
          '[data-layout-builder-reorder] [data-region_to]',
        )
        .on('click', e => {
          const delta = $(e.target).data('delta_to');
          const region = $(e.target).data('region_to');
          const precedingUuid = $(e.target).data('preceding_block_uuid');

          const block = $(e.target).closest('[data-layout-block-uuid]');
          if (precedingUuid) {
            const preceedingBlock = $(`#layout-builder [data-layout-block-uuid="${precedingUuid}"]`);
            block.before(preceedingBlock);
          }
          else {
            const regionElement = $(`#layout-builder [data-layout-delta="${delta}"] [data-region="${region}"]`);
            const firstBlock = regionElement.children('[data-layout-block-uuid]').first();
            firstBlock.before(block);
          }
          ajax({
            progress: { type: 'fullscreen' },
            url: $(e.target).attr('href'),
          }).execute();
          e.preventDefault();
        });
    },
  };
})(jQuery, Drupal);

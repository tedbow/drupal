(($, { ajax, behaviors }) => {
  /**
   * Gets the region for a element.
   *
   * @param {jQuery} element
   *   The element.
   * @return {jQuery}
   *   The components region element.
   */
  function getRegion(element) {
    return element.closest('[data-region]');
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
   * Gets the layout delta for a element.
   *
   * @param element
   * @returns {*}
   */
  function getLayoutDelta(element) {
    return element.closest('[data-layout-delta]').data('layout-delta');
  }

  /**
   *
   * @@param {jQuery} element
   * @@param {jQuery} sibling
   */
  function isElementSiblingInDirection(element, sibling, direction) {
    const elementRect = element[0].getBoundingClientRect();
    const siblingRect = sibling[0].getBoundingClientRect();
    const topDiff = elementRect.top - siblingRect.top;
    const diff = 10;
    const leftDiff = elementRect.left - siblingRect.left;
    if (topDiff < diff && topDiff > -1 * diff) {
      if (direction === 'right') {
        if (leftDiff * -1 > diff) {
          return true;
        }
      } else if (direction === 'left') {
        if (leftDiff > diff) {
          return true;
        }
      }
    }
    return false;
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
    let siblings;
    if (direction === 'up') {
      siblings = element.prevAll(selector);
    } else {
      siblings = element.nextAll(selector);
    }
    let matchSibling = null;
    siblings.each((index, sibling) => {
      if (
        !isElementSiblingInDirection(element, $(sibling), 'left') &&
        !isElementSiblingInDirection(element, $(sibling), 'right')
      ) {
        matchSibling = sibling;
        return false;
      }
    });
    return $(matchSibling);
  }

  /**
   *
   * @param {jQuery} element
   * @param direction
   */
  function findSideRegion(element, direction) {
    // Harding LTR for now
    let sibling;
    const region = getRegion(element);
    if (direction === 'left') {
      sibling = region.prev('[data-region]');
    } else {
      sibling = region.next('[data-region]');
    }
    if (
      sibling.length > 0 &&
      isElementSiblingInDirection(element, sibling, direction)
    ) {
      return sibling;
    }
  }

  /**
   * Finds the region to move to.
   *
   * @param element
   * @param direction
   * @returns {*}
   */
  function findMoveToRegion(element, direction) {
    const elementRegion = getRegion(element);
    let moveToRegion = getSiblingByDirection(
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
      return direction === 'up'
        ? sectionRegions.last()
        : sectionRegions.first();
    }
    if (direction === 'up') {
      // When moving up a row make sure to always up to the left most region.
      while (findSideRegion(moveToRegion, 'left')) {
        moveToRegion = findSideRegion(moveToRegion, 'left');
      }
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
    if (region[0] === getRegion(element)[0]) {
      if (direction === 'left' || direction === 'right') {
        const sideRegion = findSideRegion(element, direction);
        if (sideRegion) {
          return findRegionMoveToElement(sideRegion, element, direction);
        }
        return null;
      }
      if (direction === 'up') {
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
    } else if (direction === 'down') {
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
    const deltaTo = getLayoutDelta(item);
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

  function getReorderLinks(context) {
    return $(context).find(
      '[data-layout-builder-reorder] [data-layout-builder-reorder-direction]',
    );
  }

  function getLinkDirection(link) {
    return $(link).attr('data-layout-builder-reorder-direction');
  }

  function showHideReorderLinks(reorderLinks = null) {
    if (!reorderLinks) {
      reorderLinks = getReorderLinks(document.querySelector('#layout-builder'));
    }
    reorderLinks.each((index, link) => {
      const $link = $(link);
      // If there is no element for the link to move to hide the link.
      if (
        !findRegionMoveToElement(
          getRegion($link),
          $link.closest('[data-layout-block-uuid]'),
          getLinkDirection($link),
        )
      ) {
        $link.hide();
      } else {
        $link.show();
      }
    });
  }

  /**
   * Move the component in a direction.
   *
   * @param element
   * @param direction
   */
  function moveComponent(element, direction) {
    const moveToElement = findRegionMoveToElement(
      getRegion(element),
      element,
      direction,
    );
    if (moveToElement) {
      element.addClass('updating');
      const deltaFrom = getLayoutDelta(element);
      moveToElement.before(element);
      showHideReorderLinks();
      updateComponentPosition(element, deltaFrom, direction);
    }
  }

  behaviors.layoutBuilder = {
    attach(context) {
      $(window).on('resize.layout_builder', e => {
        showHideReorderLinks();
      });
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
      const reorderLinks = getReorderLinks(context);
      showHideReorderLinks(reorderLinks);
      reorderLinks.on('click', e => {
        const direction = getLinkDirection(e.target);
        moveComponent(
          $(e.target).closest('[data-layout-block-uuid]'),
          direction,
        );
        e.preventDefault();
      });
    },
  };
})(jQuery, Drupal);

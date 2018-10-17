/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, _ref) {
  var ajax = _ref.ajax,
      behaviors = _ref.behaviors;

  function getComponentRegion(component) {
    return component.closest('[data-region]');
  }

  function getSection(element) {
    return element.closest('.layout-section');
  }

  function getComponentLayoutDelta(element) {
    return element.closest('[data-layout-delta]').data('layout-delta');
  }

  function getSiblingByDirection(element, direction, selector) {
    if (direction === 'previous') {
      return element.prevAll(selector).first();
    }
    return element.nextAll(selector).first();
  }

  function findMoveToRegion(element, direction) {
    var elementRegion = getComponentRegion(element);
    var moveToRegion = getSiblingByDirection(elementRegion, direction, '[data-region]');
    if (moveToRegion.length === 0) {
      var componentSection = getSection(elementRegion);
      var moveToSection = getSiblingByDirection(componentSection, direction, '.layout-section');
      if (moveToSection.length === 0) {
        return null;
      }
      var sectionRegions = moveToSection.find('[data-region]');
      return direction === 'previous' ? sectionRegions.last() : sectionRegions.first();
    } else {
      return moveToRegion;
    }
  }

  function findRegionMoveToElement(region, element, direction) {
    var moveToElement = void 0;
    if (region[0] === getComponentRegion(element)[0]) {
      if (direction === 'previous') {
        moveToElement = element.prev('[data-layout-block-uuid]');
      } else {
        moveToElement = element.next('[data-layout-block-uuid], .new-block').next('[data-layout-block-uuid], .new-block');
      }
      if (moveToElement.length === 0) {
        var moveToRegion = findMoveToRegion(element, direction);
        if (moveToRegion) {
          return findRegionMoveToElement(moveToRegion, element, direction);
        } else {
          return null;
        }
      }
    } else {
      if (direction === 'next') {
        moveToElement = region.children('[data-layout-block-uuid], .new-block').first();
      } else {
        moveToElement = region.find('.new-block');
      }
    }

    return moveToElement.length === 0 ? null : moveToElement;
  }

  function moveComponent(element, direction) {
    var moveToElement = findRegionMoveToElement(getComponentRegion(element), element, direction);
    if (moveToElement) {
      element.addClass('updating');
      var deltaFrom = getComponentLayoutDelta(element);
      moveToElement.before(element);
      updateComponentPosition(element, deltaFrom, direction);
    }
  }

  function updateComponentPosition(item, deltaFrom) {
    var directionFocus = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'none';

    var itemRegion = item.closest('.layout-builder--layout__region');

    var deltaTo = getComponentLayoutDelta(item);
    ajax({
      progress: { type: 'fullscreen' },
      url: [item.closest('[data-layout-update-url]').data('layout-update-url'), deltaFrom, deltaTo, itemRegion.data('region'), item.data('layout-block-uuid'), directionFocus, item.prev('[data-layout-block-uuid]').data('layout-block-uuid')].filter(function (element) {
        return element !== undefined;
      }).join('/')
    }).execute();
  }

  behaviors.layoutBuilder = {
    attach: function attach(context) {
      $(context).find('.layout-builder--layout__region').sortable({
        items: '> .draggable',
        connectWith: '.layout-builder--layout__region',
        placeholder: 'ui-state-drop',

        update: function update(event, ui) {
          var itemRegion = ui.item.closest('.layout-builder--layout__region');
          if (event.target === itemRegion[0]) {
            var deltaTo = getComponentLayoutDelta(ui.item);

            var deltaFrom = ui.sender ? getComponentLayoutDelta(ui.sender) : deltaTo;
            updateComponentPosition(ui.item, deltaFrom);
          }
        }
      });
      $(context).find('[data-layout-builder-reorder] [data-layout-builder-reorder-direction]').on('click', function (e) {
        var direction = $(e.target).attr('data-layout-builder-reorder-direction');
        moveComponent($(e.target).closest('[data-layout-block-uuid]'), direction);
        e.preventDefault();
      });
    }
  };
})(jQuery, Drupal);
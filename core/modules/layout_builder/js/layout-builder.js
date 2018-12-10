/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, _ref) {
  var ajax = _ref.ajax,
      behaviors = _ref.behaviors;

  function getLayoutDelta(element) {
    return element.closest('[data-layout-delta]').data('layout-delta');
  }

  function updateComponentPosition(item, deltaFrom) {
    var itemRegion = item.closest('.layout-builder--layout__region');

    var deltaTo = getLayoutDelta(item);
    ajax({
      progress: { type: 'fullscreen' },
      url: [item.closest('[data-layout-update-url]').data('layout-update-url'), deltaFrom, deltaTo, itemRegion.data('region'), item.data('layout-block-uuid'), item.prev('[data-layout-block-uuid]').data('layout-block-uuid')].filter(function (element) {
        return element !== undefined;
      }).join('/')
    }).execute();
  }

  function watchActionLinks($elements) {
    $elements.find('a[data-layout-builder-action]').on('click', function (e) {
      var action = $(e.target).attr('data-layout-builder-action');
      $('#layout-builder').attr('data-layout-builder-current-action', action);
    });
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
            var deltaTo = getLayoutDelta(ui.item);

            var deltaFrom = ui.sender ? getLayoutDelta(ui.sender) : deltaTo;
            updateComponentPosition(ui.item, deltaFrom);
          }
        }
      });
      $(context).find('.block-destination').on('click', function (e) {
        var movingBlock = $('[data-layout-builder-moving-block]');
        var deltaFrom = movingBlock.closest('[data-layout-delta]').data('layout-delta');
        $(e.target).closest('.block-destination').replaceWith(movingBlock);
        movingBlock.removeClass('layout-builder-moving-block');
        updateComponentPosition(movingBlock, deltaFrom);
        e.preventDefault();
      });
      watchActionLinks($(context));
    }
  };

  $(document).on('drupalContextualLinkAdded', function (event, data) {
    watchActionLinks(data.$el);

    data.$el.find('.layout-builder-move-block a').on('click.layoutbuilder', function (e) {
      $('#drupal-off-canvas').dialog('close');
      $('[data-layout-builder-moving-block]').removeAttr('data-layout-builder-moving-block').removeClass('layout-builder-moving-block');
      $('.layout-builder-invalid-destination').removeClass('layout-builder-invalid-destination');
      var movingBlock = $(e.target).closest('[data-layout-block-uuid]');
      movingBlock.attr('data-layout-builder-moving-block', true);
      movingBlock.addClass('layout-builder-moving-block');
      $('.block-destination[data-preceding-block-uuid="' + movingBlock.attr('data-layout-block-uuid') + '"]').addClass('layout-builder-invalid-destination');

      if (movingBlock.prev('[data-layout-block-uuid]').length === 0) {
        movingBlock.prev('.block-destination').addClass('layout-builder-invalid-destination');
      }
      if (movingBlock.next('[data-layout-block-uuid]').length === 0) {
        movingBlock.next('.block-destination').addClass('layout-builder-invalid-destination');
      }

      e.preventDefault();
    });
  });
})(jQuery, Drupal);
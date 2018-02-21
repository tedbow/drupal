/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, Drupal) {
  Drupal.dateFormatter = Drupal.dateFormatter || {};

  Drupal.behaviors.timestampAsTimeAgo = {
    attach: function attach() {
      $('time.js-timeago').once('timeago').each(function (index, element) {
        var getTimeoutDuration = function getTimeoutDuration(value, refresh, granularity) {
          var intervals = Object.keys(value);
          var intervalsCount = intervals.length;
          var lastInterval = intervals.pop();

          if (lastInterval !== 'second') {
            if (intervalsCount === granularity) {
              $.each(Drupal.dateFormatter.intervals, function (interval, duration) {
                if (interval === lastInterval) {
                  refresh = refresh < duration ? duration : refresh;
                  return false;
                }
              });
            } else {
                var lastIntervalIndex = getTimeoutDuration.allIntervals.indexOf(lastInterval);
                var nextInterval = getTimeoutDuration.allIntervals[lastIntervalIndex + 1];
                refresh = Drupal.dateFormatter.intervals[nextInterval];
              }
          }
          return refresh * 1000;
        };
        getTimeoutDuration.allIntervals = Object.keys(Drupal.dateFormatter.intervals);

        Drupal.showTimeAgo = function (timeElement) {
          var timestamp = new Date($(timeElement).attr('datetime')).getTime();
          var timeagoSettings = JSON.parse($(timeElement).attr('data-drupal-timeago'));
          var now = Date.now();
          var options = { granularity: timeagoSettings.granularity };
          var timeago = void 0;
          var format = void 0;

          if (timestamp > now) {
            timeago = Drupal.dateFormatter.formatDiff(now, timestamp, options);
            format = timeagoSettings.format.future;
          } else {
            timeago = Drupal.dateFormatter.formatDiff(timestamp, now, options);
            format = timeagoSettings.format.past;
          }
          $(timeElement).text(Drupal.t(format, { '@interval': timeago.formatted }));

          if (timeagoSettings.refresh > 0) {
            var timeout = getTimeoutDuration(timeago.value, timeagoSettings.refresh, timeagoSettings.granularity);
            timeElement.timer = setTimeout(Drupal.showTimeAgo, timeout, timeElement);
          }
        };
        Drupal.showTimeAgo(element);
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger === 'unload') {
        var elements = $(context).find('time.js-timeago');
        elements.removeOnce('timeago');
        elements.each(function (index, element) {
          clearInterval(element.timer);
        });
      }
    }
  };

  Drupal.dateFormatter.formatDiff = function (from, to, options) {
    options = options || {};
    $.extend({ granularity: 2, strict: true }, options);

    if (options.strict && from > to) {
      return { formatted: Drupal.t('0 seconds'), value: { second: 0 } };
    }

    var output = [];
    var value = {};
    var units = void 0;
    var granularity = options.granularity;

    var diff = Math.round((to - from) / 1000);

    $.each(Drupal.dateFormatter.intervals, function (interval, duration) {
      units = Math.floor(diff / duration);
      if (units > 0) {
        diff %= units * duration;
        switch (interval) {
          case 'year':
            output.push(Drupal.formatPlural(units, '1 year', '@count years'));
            break;
          case 'month':
            output.push(Drupal.formatPlural(units, '1 month', '@count months'));
            break;
          case 'week':
            output.push(Drupal.formatPlural(units, '1 week', '@count weeks'));
            break;
          case 'day':
            output.push(Drupal.formatPlural(units, '1 day', '@count days'));
            break;
          case 'hour':
            output.push(Drupal.formatPlural(units, '1 hour', '@count hours'));
            break;
          case 'minute':
            output.push(Drupal.formatPlural(units, '1 minute', '@count minutes'));
            break;
          default:
            output.push(Drupal.formatPlural(units, '1 second', '@count seconds'));
        }
        value[interval] = units;

        granularity -= 1;
        if (granularity <= 0) {
          return false;
        }
      } else if (output.length > 0) {
        return false;
      }
    });

    if (output.length === 0) {
      return { formatted: Drupal.t('0 seconds'), value: { second: 0 } };
    }
    return { formatted: output.join(' '), value: value };
  };

  Drupal.dateFormatter.intervals = {
    year: 365 * 24 * 60 * 60,
    month: 30 * 24 * 60 * 60,
    week: 7 * 24 * 60 * 60,
    day: 24 * 60 * 60,
    hour: 60 * 60,
    minute: 60,
    second: 1
  };
})(jQuery, Drupal);
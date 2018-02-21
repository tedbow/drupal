/**
 * @file
 * Dynamic timeago formatting.
 */

(($, Drupal) => {
  Drupal.dateFormatter = Drupal.dateFormatter || { };

  /**
   * Replaces a timestamp, formatted as a date/time, with a 'time ago' string.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.timestampAsTimeAgo = {
    attach: () => {
      $('time.js-timeago')
        .once('timeago')
        .each((index, element) => {
          const getTimeoutDuration = (value, refresh, granularity) => {
            const intervals = Object.keys(value);
            const intervalsCount = intervals.length;
            const lastInterval = intervals.pop();
            // If the lowest interval of "time ago" is 'minute' or greater but
            // the refresh interval is lower, do not refresh often than the
            // duration if the lowest unit of "time ago".
            if (lastInterval !== 'second') {
              // If the "time ago" value parts count equals the granularity and
              // lowest interval duration is bigger than the configured refresh,
              // use the interval duration. For example, if the refresh duration
              // is '10 seconds', the granularity is 2 and the "time ago" is
              // '1 hour 32 minutes', do not refresh every 10 seconds but every
              //  one minute (60 seconds).
              if (intervalsCount === granularity) {
                $.each(Drupal.dateFormatter.intervals, (interval, duration) => {
                  if (interval === lastInterval) {
                    refresh = refresh < duration ? duration : refresh;
                    return false;
                  }
                });
              }
              // The "time ago" value parts count might be smaller than the
              // granularity when the lowest part is missed because is 0. In
              // this case the missed part interval duration is used as refresh.
              // For example, if the refresh is '10 seconds', the granularity is
              // 2 and the "time ago" is '59 minutes 59 seconds', on the next
              // refresh the "time ago" will be '1 hour' (because seconds are
              // not shown) but we want the next refresh to occur, not in one
              // hour, but in one minute (60 seconds).
              else {
                const lastIntervalIndex = getTimeoutDuration.allIntervals.indexOf(lastInterval);
                const nextInterval = getTimeoutDuration.allIntervals[lastIntervalIndex + 1];
                refresh = Drupal.dateFormatter.intervals[nextInterval];
              }
            }
            return refresh * 1000;
          };
          getTimeoutDuration.allIntervals = Object.keys(Drupal.dateFormatter.intervals);

          Drupal.showTimeAgo = (timeElement) => {
            const timestamp = (new Date($(timeElement).attr('datetime'))).getTime();
            const timeagoSettings = JSON.parse($(timeElement).attr('data-drupal-timeago'));
            const now = Date.now();
            const options = { granularity: timeagoSettings.granularity };
            let timeago;
            let format;

            if (timestamp > now) {
              timeago = Drupal.dateFormatter.formatDiff(now, timestamp, options);
              format = timeagoSettings.format.future;
            }
            else {
              timeago = Drupal.dateFormatter.formatDiff(timestamp, now, options);
              format = timeagoSettings.format.past;
            }
            $(timeElement).text(Drupal.t(format, { '@interval': timeago.formatted }));

            if (timeagoSettings.refresh > 0) {
              const timeout = getTimeoutDuration(
                timeago.value,
                timeagoSettings.refresh,
                timeagoSettings.granularity,
              );
              timeElement.timer = setTimeout(Drupal.showTimeAgo, timeout, timeElement);
            }
          };
          Drupal.showTimeAgo(element);
        });
    },
    detach: (context, settings, trigger) => {
      if (trigger === 'unload') {
        const elements = $(context).find('time.js-timeago');
        elements.removeOnce('timeago');
        elements.each((index, element) => {
          clearInterval(element.timer);
        });
      }
    },
  };

  /**
   * @typedef {object} timeAgoValue
   * @prop {number} [year]
   *   Years count.
   * @prop {number} [month]
   *   Months count.
   * @prop {number} [week]
   *   Weeks count.
   * @prop {number} [day]
   *   Days count.
   * @prop {number} [hour]
   *   Hours count.
   * @prop {number} [minute]
   *   Minutes count.
   * @prop {number} [second]
   *   Seconds count.
   */

  /**
   * @typedef {object} timeAgo
   * @prop {string} formatted
   *   A translated string representation of the interval.
   * @prop {timeAgoValue} value
   *   The elements composing the "time ago" interval. Example: { day: 2,
   *   hour: 2, minute: 32, second: 15 }.
   */

  /**
   * Formats a time interval between two timestamps.
   *
   * @param {number} from
   *   A UNIX timestamp, defining the from date and time.
   * @param {number} to
   *   A UNIX timestamp, defining the to date and time.
   * @param {object} [options]
   *   An optional object with additional options.
   * @param {number} [options.granularity=2]
   *   An integer value that signals how many different units to display in the
   *   string. Defaults to 2.
   * @param {boolean} [options.granularity=true]
   *   A boolean value indicating whether or not the 'from' timestamp can be
   *   after the 'to' timestamp. If true (default) and 'from' is after to', the
   *   result string will be "0 seconds". If false and 'from' is after 'to', the
   *   result string will be the formatted time difference.
   *
   * @return {timeAgo}
   *   A "time ago" type object.
   */
  Drupal.dateFormatter.formatDiff = (from, to, options) => {
    // Provide sane defaults.
    options = options || {};
    $.extend({ granularity: 2, strict: true }, options);

    if (options.strict && from > to) {
      return { formatted: Drupal.t('0 seconds'), value: { second: 0 } };
    }

    const output = [];
    const value = {};
    let units;
    let granularity = options.granularity;

    // Compute the difference in seconds.
    let diff = Math.round((to - from) / 1000);

    $.each(Drupal.dateFormatter.intervals, (interval, duration) => {
      units = Math.floor(diff / duration);
      if (units > 0) {
        diff %= (units * duration);
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
          // Limit the granularity of the output.
          return false;
        }
      }
      else if (output.length > 0) {
        // Exit if there was previous output but not any output at this level,
        // to avoid skipping levels and getting output like "1 year 1 second".
        return false;
      }
    });

    if (output.length === 0) {
      return { formatted: Drupal.t('0 seconds'), value: { second: 0 } };
    }
    return { formatted: output.join(' '), value };
  };

  /**
   * @namespace
   * @prop {number} year
   *   Year duration in seconds.
   * @prop {number} month
   *   Month duration in seconds.
   * @prop {number} week
   *   Week duration in seconds.
   * @prop {number} day
   *   Day duration in seconds.
   * @prop {number} hour
   *   Hour duration in seconds.
   * @prop {number} minute
   *   Minute duration in seconds.
   * @prop {number} second
   *   One second.
   */
  Drupal.dateFormatter.intervals = {
    year: 365 * 24 * 60 * 60,
    month: 30 * 24 * 60 * 60,
    week: 7 * 24 * 60 * 60,
    day: 24 * 60 * 60,
    hour: 60 * 60,
    minute: 60,
    second: 1,
  };
})(jQuery, Drupal);

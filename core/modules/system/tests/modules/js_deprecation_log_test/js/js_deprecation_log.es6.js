/**
 * @file
 *  Testing tools for deprecating JavaScript functions and class properties.
 */
(function() {
  if (typeof console !== 'undefined' && console.warn) {
    const originalWarnFunction = console.warn;
    console.warn = warning => {
      let warnings = JSON.parse(
        localStorage.getItem('js_deprecation_log_test.warnings') ||
          JSON.stringify([]),
      );
      warnings.push(warning);
      localStorage.setItem(
        'js_deprecation_log_test.warnings',
        JSON.stringify(warnings),
      );
      originalWarnFunction(warning);
    };
  }
})();

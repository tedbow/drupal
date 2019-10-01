/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function () {
  if (typeof console !== 'undefined' && console.warn) {
    var originalWarnFunction = console.warn;
    console.warn = function (warning) {
      var warnings = JSON.parse(localStorage.getItem('js_deprecation_test.warnings') || JSON.stringify([]));
      warnings.push(warning);
      localStorage.setItem('js_deprecation_test.warnings', JSON.stringify(warnings));
      originalWarnFunction(warning);
    };
  }
})();
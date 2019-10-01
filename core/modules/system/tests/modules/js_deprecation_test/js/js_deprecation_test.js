/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function (_ref) {
  var deprecationError = _ref.deprecationError,
      deprecatedProperty = _ref.deprecatedProperty,
      behaviors = _ref.behaviors;

  var objectWithDeprecatedProperty = deprecatedProperty({
    target: { deprecatedProperty: 'Kitten' },
    deprecatedProperty: 'deprecatedProperty',
    message: 'Here be llamas.'
  });

  behaviors.testDeprecations = {
    attach: function attach() {
      var deprecatedProperty = objectWithDeprecatedProperty.deprecatedProperty;
    }
  };
})(Drupal);
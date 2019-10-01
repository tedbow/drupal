/**
 * @file
 *  Testing tools for deprecating JavaScript functions and class properties.
 */
(function({ deprecationError, deprecatedProperty, behaviors }) {
  const objectWithDeprecatedProperty = deprecatedProperty({
    target: { deprecatedProperty: 'Kitten' },
    deprecatedProperty: 'deprecatedProperty',
    message: 'Here be llamas.',
  });

  behaviors.testDeprecations = {
    attach: () => {
      //deprecatedFunction();
      const deprecatedProperty =
        objectWithDeprecatedProperty.deprecatedProperty;
    },
  };
})(Drupal);

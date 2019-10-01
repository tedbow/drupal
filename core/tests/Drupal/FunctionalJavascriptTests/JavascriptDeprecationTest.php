<?php

namespace Drupal\FunctionalJavascriptTests;

/**
 * Tests Javascript deprecation notices.
 *
 * @group javascript
 * @group legacy
 */
class JavascriptDeprecationTest extends WebDriverTestBase {

  public static $modules = ['js_deprecation_test'];

  /**
   * @expectedDeprecation Javascript Deprecation: Here be llamas.
   */
  public function testJavascriptDeprecation() {
    $this->drupalGet('js_deprecation_test');
    // Ensure that deprecation message from previous page loads will be
    // detected.
    $this->drupalGet('user');
  }

}

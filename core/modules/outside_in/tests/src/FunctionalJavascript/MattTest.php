<?php


namespace Drupal\Tests\outside_in\FunctionalJavascript;


use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * @group matt
 */
class MattTest extends JavascriptTestBase {
  public static $modules = ['offcanvas_test'];

  public function testFail() {
    $this->drupalGet('offcanvas-js-error');
    $this->assertSession()->pageTextContains('Hello');
  }
}

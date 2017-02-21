<?php


namespace Drupal\Tests\outside_in\FunctionalJavascript;


use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * @group matt
 */
class MattTest extends JavascriptTestBase {

  public function testFail() {
    $this->drupalGet('user');

    $this->getSession()->evaluateScript('throw "errroooorrrr";');
    $this->assertSession()->pageTextContains('lkjasdf');
  }
}

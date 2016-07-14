<?php

namespace Drupal\Tests\outside_in\FunctionalJavascript;


use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

class OffCanvasTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'outside_in',
    'offcanvas_test',
  ];

  public function testOffCanvasLinks() {
    $this->placeBlock('offcanvas_links_block', ['id' => 'offcanvaslinks']);
    $this->drupalGet('');

    $page = $this->getSession()->getPage();

    $web_assert = $this->assertSession();
    $web_assert->elementNotExists('css', '#offcanvas');
    $page->clickLink('Click Me 1!');
    $condition = "(jQuery('#offcanvas').length > 0)";
    $this->assertJsCondition($condition, 5000);
    $web_assert->elementExists('css', '#offcanvas');

  }
}

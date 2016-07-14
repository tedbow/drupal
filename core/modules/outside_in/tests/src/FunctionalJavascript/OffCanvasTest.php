<?php

namespace Drupal\Tests\outside_in\FunctionalJavascript;


use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

class OffCanvasTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'offcanvas_test',
  ];

  public function testOffCanvasLinks() {
    $this->placeBlock('offcanvas_links_block');
    $this->drupalGet('');

    $page = $this->getSession()->getPage();

    $web_assert = $this->assertSession();
    $web_assert->elementNotExists('css', '#offcanvas');
    $page->clickLink('Click Me 1!');
    $web_assert->elementExists('css', '#offcanvas');

  }
}

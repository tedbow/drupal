<?php

namespace Drupal\Tests\outside_in\FunctionalJavascript;


use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

class OffCanvasTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'system',
    'toolbar',
    'outside_in',
    'offcanvas_test',
  ];

  public function testOffCanvasLinks() {
    // Enable the Bartik theme.
    // @todo Remove this when offcanvas.js is not targeting Bartik html.
    \Drupal::service('theme_installer')->install(['bartik']);
    $theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $theme_config->set('default', 'bartik');
    $theme_config->save();
    //$this->placeBlock('offcanvas_links_block', ['id' => 'offcanvaslinks']);
    $this->drupalGet('/offcanvas-test-links');

    $page = $this->getSession()->getPage();

    $this->htmlOutput($page->getContent());
    $web_assert = $this->assertSession();
    $web_assert->elementNotExists('css', '#offcanvas');
    $web_assert->elementExists('css', '#page-wrapper');
    $this->htmlOutput($page->getContent());
    $page->clickLink('Click Me 1!');
    $this->waitForAjaxToFinish();

    $condition = "(jQuery('#offcanvas').length > 0)";
    $this->assertJsCondition($condition, 5000);
    $web_assert->elementExists('css', '#offcanvas');
  }

  protected function htmlOutput($message) {
    $this->htmlOutputCounter++;
    $message = '<hr />ID #' . $this->htmlOutputCounter . ' (<a href="' . $this->htmlOutputClassName . '-' . ($this->htmlOutputCounter - 1) . '-' . $this->htmlOutputTestId . '.html">Previous</a> | <a href="' . $this->htmlOutputClassName . '-' . ($this->htmlOutputCounter + 1) . '-' . $this->htmlOutputTestId . '.html">Next</a>)<hr />' . $message;
    $html_output_filename = $this->htmlOutputClassName . '-' . $this->htmlOutputCounter . '-' . $this->htmlOutputTestId . '.html';
    file_put_contents('/var/www/' . $html_output_filename, $message);
  }

  /**
   * Waits for jQuery to become active and animations to complete.
   */
  protected function waitForAjaxToFinish() {
    $condition = "(0 === jQuery.active && 0 === jQuery(':animated').length)";
    $this->assertJsCondition($condition, 10000);
  }


}

<?php

namespace Drupal\Tests\outside_in\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests the off-canvas tray functionality.
 */
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

  /**
   * Tests that regular non-contextual links will work with the off-canvas tray.
   */
  public function testOffCanvasLinks() {
    // @todo Add other themes to test against.
    $themes = ['bartik', 'stark'];
    // @todo Add RTL Language test for each theme.
    // Test the same functionality on multiple themes
    foreach ($themes as $theme) {
      // Enable the theme.
      \Drupal::service('theme_installer')->install([$theme]);
      $theme_config = \Drupal::configFactory()->getEditable('system.theme');
      $theme_config->set('default', $theme);
      $theme_config->save();
      $this->drupalGet('/offcanvas-test-links');

      $page = $this->getSession()->getPage();
      $web_assert = $this->assertSession();

      // Make sure off-canvas tray is on page when first loaded.
      $web_assert->elementNotExists('css', '#offcanvas');

      // Check opening and closing with two separate links.
      // Make sure tray updates to new content.
      foreach (['1', '2'] as $link_index) {
        // Click the first test like that should open the page.
        $page->clickLink("Click Me $link_index!");
        $this->waitForOffCanvasToOpen();

        // Check that the canvas is not on the page.
        $web_assert->elementExists('css', '#offcanvas');
        // Check that response text is on page.
        $web_assert->pageTextContains("Thing $link_index says hello");
        $offcanvas_tray = $page->findById('offcanvas');

        // Check that tray is visible.
        $this->assertEquals(TRUE,$offcanvas_tray->isVisible());
        $header_text = $offcanvas_tray->findById('offcanvas-header')->getText();

        // Check that header is correct.
        $this->assertEquals("Thing $link_index", $header_text);
        $tray_text = $offcanvas_tray->find('css', '.content')->getText();
        $this->assertEquals("Thing $link_index says hello", $tray_text);

        // Close the tray.
        // @todo Should the close have an id?
        $offcanvas_tray->find('css', '.offcanvasClose')->press();
        // Wait for animation to be done.
        $this->waitForOffCanvasToClose();
        // Make sure canvas doesn't exist after closing.
        $web_assert->elementNotExists('css', '#offcanvas');
      }

      $this->verbose('Test theme: ' . $theme);
    }
  }

  /**
   * Waits for Off-canvas tray to close.
   */
  protected function waitForOffCanvasToClose() {
    $condition = "(jQuery('#offcanvas').length == 0)";
    $this->assertJsCondition($condition, 5000);
  }

  /**
   * Waits for Off-canvas tray to open.
   */
  protected function waitForOffCanvasToOpen() {
    $condition = "(jQuery('#offcanvas').length > 0)";
    $this->assertJsCondition($condition, 5000);
  }

}

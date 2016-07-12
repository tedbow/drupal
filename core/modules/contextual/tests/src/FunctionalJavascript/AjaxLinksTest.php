<?php

namespace Drupal\block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\simpletest\BlockCreationTrait;

/**
 * Tests block configure modal links.
 */
class AjaxLinksTest extends JavascriptTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'block', 'contextual', 'node'];


  public function testConfigureBlock() {
    $this->placeBlock('system_powered_by_block', ['id' => 'poweredbydrupal']);
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer site configuration',
      'access contextual links',
    ]);
    $this->drupalLogin($admin_user);

    $button_selector = '[data-contextual-id="block:block=poweredbydrupal:langcode=en"] button';
    $this->waitForAjaxToFinish($button_selector);
    $this->assertElementPresent('.contextual-links a.use-ajax[data-dialog-type="modal"]');


    $this->assertElementPresent($button_selector);
    $this->assertElementVisible($button_selector);
    $session = $this->getSession();

    $page = $session->getPage();
    /** @var \Zumba\Mink\Driver\PhantomJSDriver $driver */
    $driver = $session->getDriver();
    $driver->focus($this->cssSelectToXpath($button_selector));
    $page->pressButton('Open configuration options');

    $configure_link = $session->getPage()->findLink('Configure block');
    $this->assertTrue($configure_link->isVisible());
    $configure_link->click();
  }

  /**
   * Waits for jQuery to become active and animations to complete.
   */
  protected function waitForAjaxToFinish($button_selector) {
    $condition = "(jQuery('$button_selector').length > 0)";
    $this->assertJsCondition($condition, 10000);
  }

}

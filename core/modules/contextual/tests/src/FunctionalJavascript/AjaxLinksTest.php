<?php

namespace Drupal\Tests\contextual\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\simpletest\BlockCreationTrait;

/**
 * Tests block configure modal links.
 *
 * @group contextual
 */
class AjaxLinksTest extends JavascriptTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'block', 'contextual', 'node', 'contextual_ajax_test'];


  public function testConfigureBlock() {
    $this->placeBlock('ajax_test_block', ['id' => 'ajaxtestblock']);
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer site configuration',
      'access contextual links',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('');
    $button_selector = '[data-contextual-id="block:block=ajaxtestblock:langcode=en"] button';
    $this->waitForAjaxToFinish($button_selector);
    $condition = "(jQuery('.contextual-links a.use-ajax[data-dialog-type=\"modal\"]').length > 0)";
    $this->assertJsCondition($condition, 50000);

    $this->assertElementPresent('.contextual-links a.use-ajax[data-dialog-type="modal"]');

    $this->assertElementPresent($button_selector);
    $this->assertElementVisible($button_selector);
    $session = $this->getSession();

    $page = $session->getPage();
    /** @var \Zumba\Mink\Driver\PhantomJSDriver $driver */
    $driver = $session->getDriver();
    //$driver->focus($this->cssSelectToXpath($button_selector));
    $button = $page->find('css', 'button');
    $button->click();

    $configure_link = $session->getPage()->findLink('Configure block');
    $this->assertTrue($configure_link->isVisible());
    $configure_link->click();

    \Drupal::entityTypeManager()->getStorage('entity_type')->load('id')->delete();
  }

  /**
   * Waits for jQuery to become active and animations to complete.
   */
  protected function waitForAjaxToFinish($button_selector) {
    $condition = "(jQuery('{$button_selector}').length > 0)";
    $this->assertJsCondition($condition, 5000);
    $condition = "(jQuery('.contextual-links a.use-ajax[data-dialog-type=\"modal\"]').length > 0)";
    $this->assertJsCondition($condition, 50000);
  }

}

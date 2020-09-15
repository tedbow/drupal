<?php

namespace Drupal\Tests\update\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests of PSA functionality.
 *
 * @group update
 */
class PsaTest extends BrowserTestBase {

  use AssertMailTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update', 'psa_test'];

  /**
   * A user with permission to administer site configuration and updates.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A working test PSA endpoint.
   *
   * @var string
   */
  protected $workingEndpoint;

  /**
   * A non-working test PSA endpoint.
   *
   * @var string
   */
  protected $nonWorkingEndpoint;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    $this->user = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'administer software updates',
    ]);
    $this->drupalLogin($this->user);
    $this->workingEndpoint = $this->buildUrl(Url::fromRoute('psa_test.json_test_controller'));
    $this->nonWorkingEndpoint = $this->buildUrl(Url::fromRoute('psa_test.json_test_denied_controller'));
  }

  /**
   * Tests that a PSA is displayed.
   */
  public function testPsa() {
    $assert = $this->assertSession();
    // Setup test PSA endpoint.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->workingEndpoint)
      ->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextContains('Critical Release - SA-2019-02-19');
    $assert->pageTextContains('Critical Release - PSA-Really Old');
    $assert->pageTextNotContains('Node - Moderately critical - Access bypass - SA-CONTRIB-2019');
    $assert->pageTextNotContains('Views - Moderately critical - Access bypass - SA-CONTRIB-2019');

    // Test site status report.
    $this->drupalGet(Url::fromRoute('system.status'));
    $assert->pageTextContains('3 urgent announcements require your attention:');

    // Test cache.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->nonWorkingEndpoint)
      ->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextContains('Critical Release - SA-2019-02-19');
    $assert->pageTextContains('Critical Release - PSA-Really Old');
    $assert->pageTextNotContains('Node - Moderately critical - Access bypass - SA-CONTRIB-2019');
    $assert->pageTextNotContains('Views - Moderately critical - Access bypass - SA-CONTRIB-2019');

    // Test transmit errors with JSON endpoint.
    drupal_flush_all_caches();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextContains("Drupal PSA endpoint {$this->nonWorkingEndpoint} is unreachable.");
    $assert->pageTextNotContains('Critical Release - SA-2019-02-19');

    // Test disabling PSAs.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->workingEndpoint)
      ->save();
    $this->setSettingsCheckbox('psa_enable', FALSE);
    drupal_flush_all_caches();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextNotContains('Critical Release - PSA-2019-02-19');
    $this->drupalGet(Url::fromRoute('system.status'));
    $assert->pageTextNotContains('urgent announcements require your attention');
  }

  /**
   * Tests sending PSA email notifications.
   */
  public function testPsaMail() {
    // Setup test PSA endpoint.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->workingEndpoint)
      ->save();
    // Setup a default destination email address.
    $this->config('update.settings')
      ->set('notification.emails', ['admin@example.com'])
      ->save();

    // Test PSAs on admin pages.
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->pageTextContains('Critical Release - SA-2019-02-19');

    // Email should be sent.
    $notify = $this->container->get('update.psa_notify');
    $notify->send();
    $this->assertCount(1, $this->getMails());
    $this->assertMailString('subject', '3 urgent Drupal announcements require your attention', 1);
    $this->assertMailString('body', 'Critical Release - SA-2019-02-19', 1);

    // No email should be sent if PSA's are disabled.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->container->get('state')->delete('update_psa.notify_last_check');
    $this->setSettingsCheckbox('psa_notify', FALSE);
    $notify->send();
    $this->assertCount(0, $this->getMails());
  }

  private function setSettingsCheckbox(string $checkbox, bool $enable) {
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/reports/updates/settings');
    if ($enable) {
      $page->checkField($checkbox);
    }
    else {
      $page->uncheckField($checkbox);
    }
    $page->pressButton('Save configuration');
  }

}

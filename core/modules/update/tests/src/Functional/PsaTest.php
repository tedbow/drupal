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
  protected static $modules = ['update'];

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
   * A test end PSA endpoint that returns invalid JSON.
   *
   * @var string
   */
  protected $invalidJsonEndpoint;

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
    $fixtures_path = $this->baseUrl . '/core/modules/update/tests/fixtures/psa_feed';
    $this->workingEndpoint = "$fixtures_path/valid.json";
    $this->nonWorkingEndpoint = "$fixtures_path/non-existent.json";
    $this->invalidJsonEndpoint = "$fixtures_path/invalid.json";

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
    $assert->pageTextContains('Critical Release - SA-2019-02-19');
    $assert->pageTextContains('Critical Release - PSA-Really Old');

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
    $assert->pageTextContains('Unable to retrieve PSA information from ' . $this->nonWorkingEndpoint);
    $assert->pageTextNotContains('Critical Release - SA-2019-02-19');

    // Test disabling PSAs.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->workingEndpoint)
      ->save();
    $this->setSettingsViaForm('psa_enable', FALSE);
    drupal_flush_all_caches();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextNotContains('Critical Release - PSA-2019-02-19');
    $this->drupalGet(Url::fromRoute('system.status'));
    $assert->pageTextContains(' 3 urgent announcements require your attention');

    // Test a PSA endpoint that returns invalid JSON.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->invalidJsonEndpoint)
      ->save();
    $this->setSettingsViaForm('psa_enable', TRUE);
    drupal_flush_all_caches();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextNotContains('Critical Release - PSA-2019-02-19');
    $assert->pageTextContains('Drupal PSA JSON is malformed.');
    $this->drupalGet(Url::fromRoute('system.status'));
    $assert->pageTextContains('Drupal PSA JSON is malformed.');
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
    $this->setSettingsViaForm('psa_notify', FALSE);
    $notify->send();
    $this->assertCount(0, $this->getMails());
  }

  /**
   * Tests sending an email when the PSA JSON is invalid.
   */
  public function testInvalidJsonEmail() {
    // Setup a default destination email address.
    $this->config('update.settings')
      ->set('notification.emails', ['admin@example.com'])
      ->save();
    $this->setSettingsViaForm('psa_notify', TRUE);
    $this->config('update.settings')
      ->set('psa.endpoint', $this->invalidJsonEndpoint)
      ->save();
    $this->container->get('cache.default')->delete('updates_psa');
    $notify = $this->container->get('update.psa_notify');
    $notify->send();
    $this->assertCount(1, $this->getMails());
    $this->assertMailString('subject', '1 urgent Drupal announcement requires your attention', 1);
    $this->assertMailString('body', 'Drupal PSA JSON is malformed.', 1);
  }

  /**
   * Sets a PSA setting via the settings form.
   *
   * @param string $checkbox
   *   The name of the checkbox.
   * @param bool $enable
   *   Whether the setting should be enabled.
   */
  private function setSettingsViaForm(string $checkbox, bool $enable) {
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

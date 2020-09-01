<?php

namespace Drupal\Tests\update\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests notification emails for PSAs.
 *
 * @group update
 */
class PsaNotifyTest extends BrowserTestBase {
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
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    // Setup test PSA endpoint.
    $end_point = $this->buildUrl(Url::fromRoute('psa_test.json_test_controller'));
    $this->config('update.settings')
      ->set('psa.endpoint', $end_point)
      ->save();
    // Setup a default destination email address.
    $this->config('update.settings')
      ->set('notification.emails', ['admin@example.com'])
      ->save();

    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests sending PSA email notifications.
   */
  public function testPsaMail() {
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
    $this->config('update.settings')
      ->set('psa.enable', FALSE)
      ->save();
    $notify->send();
    $this->assertCount(0, $this->getMails());
  }

}

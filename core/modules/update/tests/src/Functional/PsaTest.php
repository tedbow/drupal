<?php

namespace Drupal\Tests\update\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests of PSA functionality.
 *
 * @group update
 */
class PsaTest extends BrowserTestBase {

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
    $this->user = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'administer software updates',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that a PSA is displayed.
   */
  public function testPsa() {
    // Setup test PSA endpoint.
    $end_point = $this->buildUrl(Url::fromRoute('psa_test.json_test_controller'));
    $this->config('update.settings')
      ->set('psa.endpoint', $end_point)
      ->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->pageTextContains('Critical Release - SA-2019-02-19');
    $this->assertSession()->pageTextContains('Critical Release - PSA-Really Old');
    $this->assertSession()->pageTextNotContains('Node - Moderately critical - Access bypass - SA-CONTRIB-2019');
    $this->assertSession()->pageTextNotContains('Views - Moderately critical - Access bypass - SA-CONTRIB-2019');

    // Test site status report.
    $this->drupalGet(Url::fromRoute('system.status'));
    $this->assertSession()->pageTextContains('3 urgent announcements require your attention:');

    // Test cache.
    $end_point = $this->buildUrl(Url::fromRoute('psa_test.json_test_denied_controller'));
    $this->config('update.settings')
      ->set('psa.endpoint', $end_point)
      ->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->pageTextContains('Critical Release - SA-2019-02-19');

    // Test transmit errors with JSON endpoint.
    drupal_flush_all_caches();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->pageTextContains("Drupal PSA endpoint $end_point is unreachable.");

    // Test disabling PSAs.
    $end_point = $this->buildUrl(Url::fromRoute('psa_test.json_test_controller'));
    $this->config('update.settings')
      ->set('psa.endpoint', $end_point)
      ->set('psa.enable', FALSE)
      ->save();
    drupal_flush_all_caches();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->pageTextNotContains('Critical Release - PSA-2019-02-19');
    $this->drupalGet(Url::fromRoute('system.status'));
    $this->assertSession()->pageTextNotContains('urgent announcements require your attention');
  }

}

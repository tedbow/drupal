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
  protected static $modules = [
    'update',
    'aaa_update_test',
    'update_test',
  ];

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
   * A working test PSA endpoint that has 1 more item than $workingEndpoint.
   *
   * @var string
   */
  protected $workingEndpointPlus1;

  /**
   * A non-working test PSA endpoint.
   *
   * @var string
   */
  protected $nonWorkingEndpoint;

  /**
   * A test PSA endpoint that returns invalid JSON.
   *
   * @var string
   */
  protected $invalidJsonEndpoint;

  /**
   * The key/value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $tempStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Alter the 'aaa_update_test' to use the 'aaa_update_project' project name.
    // The PSA feed will match project name and not extension name.
    $system_info = [
      'aaa_update_test' => [
        'project' => 'aaa_update_project',
        'version' => '8.x-1.1',
        'hidden' => FALSE,
      ],
      'bbb_update_test' => [
        'project' => 'bbb_update_project',
        'version' => '8.x-1.1',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
    $this->user = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'administer software updates',
    ]);
    $this->drupalLogin($this->user);
    $fixtures_path = $this->baseUrl . '/core/modules/update/tests/fixtures/psa_feed';
    $this->workingEndpoint = $this->buildUrl('/update-test-json/valid');
    $this->workingEndpointPlus1 = $this->buildUrl('/update-test-json/valid_plus1');
    $this->nonWorkingEndpoint = $this->buildUrl('/update-test-json/missing');
    $this->invalidJsonEndpoint = "$fixtures_path/invalid.json";

    $this->tempStore = $this->container->get('keyvalue.expirable')->get('update');

  }

  /**
   * Tests that a PSA is displayed.
   */
  public function testPsa(): void {
    $assert = $this->assertSession();
    // Setup test PSA endpoint.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->workingEndpoint)
      ->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextContains('Critical Release - SA-2019-02-19');
    $assert->pageTextContains('Critical Release - PSA-Really Old');
    $assert->pageTextContains('AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');
    $assert->pageTextContains('BBB Update project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');
    $assert->pageTextNotContains('Node - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');
    $assert->pageTextNotContains('Views - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');

    // Test site status report.
    $this->drupalGet(Url::fromRoute('system.status'));
    file_put_contents("/Users/ted.bowman/sites/test.html", $this->getSession()->getPage()->getOuterHtml());
    $assert->pageTextContains('4 urgent announcements require your attention:');
    $assert->pageTextContains('Critical Release - SA-2019-02-19');
    $assert->pageTextContains('Critical Release - PSA-Really Old');
    $assert->pageTextContains('AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');
    $assert->pageTextContains('BBB Update project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');

    // Test cache.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->nonWorkingEndpoint)
      ->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextContains('Critical Release - SA-2019-02-19');
    $assert->pageTextContains('Critical Release - PSA-Really Old');
    $assert->pageTextNotContains('Node - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');
    $assert->pageTextNotContains('Views - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02');

    // Tests transmit errors with a JSON endpoint.
    $this->tempStore->delete('updates_psa');
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextNotContains('Critical Release - SA-2019-02-19');

    // Test a PSA endpoint that returns invalid JSON.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->invalidJsonEndpoint)
      ->save();
    $this->tempStore->delete('updates_psa');
    // On admin pages no message should be displayed if the feed is malformed.
    $this->drupalGet(Url::fromRoute('system.admin'));
    $assert->pageTextNotContains('Critical Release - PSA-2019-02-19');
    $assert->pageTextNotContains('Drupal PSA JSON is malformed.');
    // On the status report there should be a message for a malformed feed.
    $this->drupalGet(Url::fromRoute('system.status'));
    $assert->pageTextContains('Drupal PSA JSON is malformed.');
  }

  /**
   * Tests sending PSA email notifications.
   */
  public function testPsaMail(): void {
    // Set up test PSA endpoint.
    $this->config('update.settings')
      ->set('psa.endpoint', $this->workingEndpoint)
      ->save();
    // Setup a default destination email address.
    $this->config('update.settings')
      ->set('notification.emails', ['admin@example.com'])
      ->save();

    // Confirm that PSA cache does not exist.
    $this->assertNull($this->tempStore->get('updates_psa'));

    // Test PSAs on admin pages.
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->pageTextContains('Critical Release - SA-2019-02-19');
    // Confirm that the PSA cache has been set.
    $this->assertNotEmpty($this->tempStore->get('updates_psa'));

    // Email should be sent.
    $this->container->get('cron')->run();
    $this->assertCount(1, $this->getPsaEmails());
    $this->assertMailString('subject', '4 urgent security announcements require your attention', 1);
    $this->assertMailString('body', 'Critical Release - SA-2019-02-19', 1);
    $this->assertMailString('body', 'Critical Release - PSA-Really Old', 1);
    $this->assertMailString('body', 'AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02', 1);
    $this->assertMailString('body', 'AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02', 1);


    // Deleting the PSA cache will not result in another email if the messages
    // have not changed.
    // @todo Replace deleting the cache directly in the test with faking a later
    //   date and letting the cache item expire in
    //   https://www.drupal.org/node/3113971.
    $this->tempStore->delete('updates_psa');
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->container->get('cron')->run();
    $this->assertCount(0, $this->getPsaEmails());

    // Deleting the PSA tempstore item will result in another email if the
    // messages have changed.
    $this->tempStore->delete('updates_psa');
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->config('update.settings')->set('psa.endpoint', $this->workingEndpointPlus1)->save();
    $this->container->get('cron')->run();
    $this->assertCount(1, $this->getPsaEmails());
    $this->assertMailString('subject', '5 urgent security announcements require your attention', 1);
    $this->assertMailString('body', 'Critical Release - SA-2019-02-19', 1);
    $this->assertMailString('body', 'Critical Release - PSA-Really Old', 1);
    $this->assertMailString('body', 'AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02', 1);
    $this->assertMailString('body', 'AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019-02-02', 1);
    $this->assertMailString('body', 'Critical Release - PSA because 2020', 1);
  }

  /**
   * Tests sending an email when the PSA JSON is invalid.
   */
  public function testInvalidJsonEmail(): void {
    // Setup a default destination email address.
    $this->config('update.settings')
      ->set('notification.emails', ['admin@example.com'])
      ->save();
    $this->config('update.settings')
      ->set('psa.endpoint', $this->invalidJsonEndpoint)
      ->save();
    $this->tempStore->delete('updates_psa');
    $this->container->get('cron')->run();
    $this->assertCount(0, $this->getPsaEmails());
  }

  /**
   * Gets an array of 'update_psa_notify' emails sent during this test case.
   *
   * @return array
   *   An array containing email messages captured during the current test.
   */
  protected function getPsaEmails(): array {
    return $this->getMails(['id' => 'update_psa_notify']);
  }

}

<?php

namespace Drupal\Tests\update\Functional;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests the Update Manager module through a series of functional tests using
 * mock XML data.
 *
 * @group update
 */
class UpdateSemanticTestBase extends UpdateTestBase {

  use CronRunTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['update_test', 'update', 'language', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The project title.
   *
   * @var string
   */
  protected $projectTitle;

  protected function setUp() {
    parent::setUp();
    $admin_user = $this->drupalCreateUser(['administer site configuration', 'administer modules', 'administer themes']);
    $this->drupalLogin($admin_user);
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Sets the version to x.x.x when no project-specific mapping is defined.
   *
   * @param string $version
   *   The version.
   */
  protected function setSystemInfo($version) {
    $setting = [
      '#all' => [
        'version' => $version,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $setting)->save();
  }

  /**
   * Tests the Update Manager module when no updates are available.
   *
   * The XML fixture file 'drupal.1.0.xml' which is one of the XML files this
   * test uses also contains 2 extra releases that are newer than '8.0.1'. These
   * releases will not show as available updates because of the following
   * reasons:
   * - '8.0.2' is an unpublished release.
   * - '8.0.3' is marked as 'Release type' 'Unsupported'.
   */
  public function testNoUpdatesAvailable() {
    foreach ([0, 1] as $minor_version) {
      foreach ([0, 1] as $patch_version) {
        foreach (['-alpha1', '-beta1', ''] as $extra_version) {
          $this->setProjectInfo("8.$minor_version.$patch_version" . $extra_version);
          $this->refreshUpdateStatus([$this->updateProject => "$minor_version.$patch_version" . $extra_version]);
          $this->standardTests();
          // The XML test fixtures for this method all contain the '8.2.0'
          // release but because '8.2.0' is not in a supported branch it will
          // not be in the available updates.
          $this->assertUpdateTableElementContains('8.2.0');
          $this->assertUpdateTableTextContains(t('Up to date'));
          $this->assertUpdateTableTextNotContains(t('Update available'));
          $this->assertUpdateTableTextNotContains(t('Security update required!'));
          $this->assertUpdateTableElementContains('check.svg', 'Check icon was found.');
        }
      }
    }
  }

  /**
   * Tests the Update Manager module when one normal update is available.
   */
  public function testNormalUpdateAvailable() {
    $this->setProjectInfo('8.0.0');

    // Ensure that the update check requires a token.
    $this->drupalGet('admin/reports/updates/check');
    $this->assertResponse(403, 'Accessing admin/reports/updates/check without a CSRF token results in access denied.');

    foreach ([0, 1] as $minor_version) {
      foreach (['-alpha1', '-beta1', ''] as $extra_version) {
        $full_version = "8.$minor_version.1$extra_version";
        $this->refreshUpdateStatus([$this->updateProject => "$minor_version.1" . $extra_version]);
        $this->standardTests();
        $this->drupalGet('admin/reports/updates');
        $this->clickLink(t('Check manually'));
        $this->checkForMetaRefresh();
        $this->assertUpdateTableTextNotContains(t('Security update required!'));
        // The XML test fixtures for this method all contain the '8.2.0' release
        // but because '8.2.0' is not in a supported branch it will not be in
        // the available updates.
        $this->assertNoRaw('8.2.0');
        switch ($minor_version) {
          case 0:
            // Both stable and unstable releases are available.
            // A stable release is the latest.
            if ($extra_version == '') {
              $this->assertUpdateTableTextNotContains(t('Up to date'));
              $this->assertUpdateTableTextContains(t('Update available'));
              $this->assertVersionUpdateLinks('Recommended version:', $full_version);
              $this->assertUpdateTableTextNotContains(t('Latest version:'));
              $this->assertUpdateTableElementContains('warning.svg', 'Warning icon was found.');
            }
            // Only unstable releases are available.
            // An unstable release is the latest.
            else {
              $this->assertUpdateTableTextContains(t('Up to date'));
              $this->assertUpdateTableTextNotContains(t('Update available'));
              $this->assertUpdateTableTextNotContains(t('Recommended version:'));
              $this->assertVersionUpdateLinks('Latest version:', $full_version);
              $this->assertUpdateTableElementContains('check.svg', 'Check icon was found.');
            }
            break;
          case 1:
            // Both stable and unstable releases are available.
            // A stable release is the latest.
            if ($extra_version == '') {
              $this->assertUpdateTableTextNotContains(t('Up to date'));
              $this->assertUpdateTableTextContains(t('Update available'));
              $this->assertVersionUpdateLinks('Recommended version:', $full_version);
              $this->assertUpdateTableTextNotContains(t('Latest version:'));
              $this->assertUpdateTableElementContains('warning.svg', 'Warning icon was found.');
            }
            // Both stable and unstable releases are available.
            // An unstable release is the latest.
            else {
              $this->assertUpdateTableTextNotContains(t('Up to date'));
              $this->assertUpdateTableTextContains(t('Update available'));
              $this->assertVersionUpdateLinks('Recommended version:', '8.1.0');
              $this->assertVersionUpdateLinks('Latest version:', $full_version);
              $this->assertUpdateTableElementContains('warning.svg', 'Warning icon was found.');
            }
            break;
        }
      }
    }
  }

  /**
   * Tests the Update Manager module when a major update is available.
   */
  public function testMajorUpdateAvailable() {
    foreach ([0, 1] as $minor_version) {
      foreach ([0, 1] as $patch_version) {
        foreach (['-alpha1', '-beta1', ''] as $extra_version) {
          $this->setProjectInfo("8.$minor_version.$patch_version" . $extra_version);
          $this->refreshUpdateStatus([$this->updateProject => '9']);
          $this->standardTests();
          $this->drupalGet('admin/reports/updates');
          $this->clickLink(t('Check manually'));
          $this->checkForMetaRefresh();
          $this->assertUpdateTableTextNotContains(t('Security update required!'));
          $this->assertUpdateTableElementContains(Link::fromTextAndUrl('9.0.0', Url::fromUri("http://example.com/{$this->updateProject}-9-0-0-release"))->toString(), 'Link to release appears.');
          $this->assertUpdateTableElementContains(Link::fromTextAndUrl(t('Download'), Url::fromUri("http://example.com/{$this->updateProject}-9-0-0.tar.gz"))->toString(), 'Link to download appears.');
          $this->assertUpdateTableElementContains(Link::fromTextAndUrl(t('Release notes'), Url::fromUri("http://example.com/{$this->updateProject}-9-0-0-release"))->toString(), 'Link to release notes appears.');
          $this->assertUpdateTableTextNotContains(t('Up to date'));
          $this->assertUpdateTableTextContains(t('Not supported!'));
          $this->assertUpdateTableTextContains(t('Recommended version:'));
          $this->assertUpdateTableTextNotContains(t('Latest version:'));
          $this->assertUpdateTableElementContains('error.svg', 'Error icon was found.');
        }
      }
    }
  }

  /**
   * Tests the Update Manager module when a security update is available.
   *
   * @param string $site_patch_version
   *   The patch version to set the site to for testing.
   * @param string[] $expected_security_releases
   *   The security releases, if any, that the status report should recommend.
   * @param string $expected_update_message_type
   *   The type of update message expected.
   * @param string $fixture
   *   The test fixture that contains the test XML.
   *
   * @dataProvider securityUpdateAvailabilityProvider
   */
  public function testSecurityUpdateAvailability($site_patch_version, array $expected_security_releases, $expected_update_message_type, $fixture) {
    $this->setProjectInfo("8.$site_patch_version");
    $this->refreshUpdateStatus([$this->updateProject => $fixture]);
    $this->assertSecurityUpdates('{$this->updateProject}-8', $expected_security_releases, $expected_update_message_type, 'table.update');
  }

  /**
   * Data provider method for testSecurityUpdateAvailability().
   *
   * These test cases rely on the following fixtures containing the following
   * releases:
   * - drupal.sec.0.1_0.2.xml
   *   - 8.0.2 Security update
   *   - 8.0.1 Security update, Insecure
   *   - 8.0.0 Insecure
   * - drupal.sec.0.2.xml
   *   - 8.0.2 Security update
   *   - 8.0.1 Insecure
   *   - 8.0.0 Insecure
   * - drupal.sec.0.2-rc2.xml
   *   - 8.2.0-rc2 Security update
   *   - 8.2.0-rc1 Insecure
   *   - 8.2.0-beta2 Insecure
   *   - 8.2.0-beta1 Insecure
   *   - 8.2.0-alpha2 Insecure
   *   - 8.2.0-alpha1 Insecure
   *   - 8.1.2 Security update
   *   - 8.1.1 Insecure
   *   - 8.1.0 Insecure
   *   - 8.0.2 Security update
   *   - 8.0.1 Insecure
   *   - 8.0.0 Insecure
   * - drupal.sec.1.2.xml
   *   - 8.1.2 Security update
   *   - 8.1.1 Insecure
   *   - 8.1.0 Insecure
   *   - 8.0.2
   *   - 8.0.1
   *   - 8.0.0
   * - drupal.sec.1.2_insecure.xml
   *   - 8.1.2 Security update
   *   - 8.1.1 Insecure
   *   - 8.1.0 Insecure
   *   - 8.0.2 Insecure
   *   - 8.0.1 Insecure
   *   - 8.0.0 Insecure
   * - drupal.sec.1.2_insecure-unsupported
   *   This file has the exact releases as drupal.sec.1.2_insecure.xml. It has a
   *   different value for 'supported_branches' that does not contain '8.0.'.
   *   It is used to ensure that the "Security update required!" is displayed
   *   even if the currently installed version is in an unsupported branch.
   * - drupal.sec.0.2-rc2-b.xml
   *   - 8.2.0-rc2
   *   - 8.2.0-rc1
   *   - 8.2.0-beta2
   *   - 8.2.0-beta1
   *   - 8.2.0-alpha2
   *   - 8.2.0-alpha1
   *   - 8.1.2 Security update
   *   - 8.1.1 Insecure
   *   - 8.1.0 Insecure
   *   - 8.0.2 Security update
   *   - 8.0.1 Insecure
   *   - 8.0.0 Insecure
   */
  public function securityUpdateAvailabilityProvider() {
    $test_cases = [
      // Security release available for site minor release 0.
      // No releases for next minor.
      '0.0, 0.2' => [
        'site_patch_version' => '0.0',
        'expected_security_releases' => ['0.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.0.2',
      ],
      // Site on latest security release available for site minor release 0.
      // Minor release 1 also has a security release, and the current release
      // is marked as insecure.
      '0.2, 0.2' => [
        'site_patch_version' => '0.2',
        'expected_security_release' => ['1.2', '2.0-rc2'],
        'expected_update_message_type' => static::UPDATE_AVAILABLE,
        'fixture' => 'sec.0.2-rc2',
      ],
      // Two security releases available for site minor release 0.
      // 0.1 security release marked as insecure.
      // No releases for next minor.
      '0.0, 0.1 0.2' => [
        'site_patch_version' => '0.0',
        'expected_security_releases' => ['0.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.0.1_0.2',
      ],
      // Security release available for site minor release 1.
      // No releases for next minor.
      '1.0, 1.2' => [
        'site_patch_version' => '1.0',
        'expected_security_releases' => ['1.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.1.2',
      ],
      // Security release available for site minor release 0.
      // Security release also available for next minor.
      '0.0, 0.2 1.2' => [
        'site_patch_version' => '0.0',
        'expected_security_releases' => ['0.2', '1.2', '2.0-rc2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.0.2-rc2',
      ],
      // No newer security release for site minor 1.
      // Previous minor has security release.
      '1.2, 0.2 1.2' => [
        'site_patch_version' => '1.2',
        'expected_security_releases' => [],
        'expected_update_message_type' => static::UPDATE_NONE,
        'fixture' => 'sec.0.2-rc2',
      ],
      // No security release available for site minor release 0.
      // Security release available for next minor.
      '0.0, 1.2, insecure' => [
        'site_patch_version' => '0.0',
        'expected_security_releases' => ['1.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.1.2_insecure',
      ],
      // No security release available for site minor release 0.
      // Site minor is not a supported branch.
      // Security release available for next minor.
      '0.0, 1.2, insecure-unsupported' => [
        'site_patch_version' => '0.0',
        'expected_security_releases' => ['1.2'],
        'expected_update_message_type' => static::SECURITY_UPDATE_REQUIRED,
        'fixture' => 'sec.1.2_insecure-unsupported',
      ],
      // All releases for minor 0 are secure.
      // Security release available for next minor.
      '0.0, 1.2, secure' => [
        'site_patch_version' => '0.0',
        'expected_security_release' => ['1.2'],
        'expected_update_message_type' => static::UPDATE_AVAILABLE,
        'fixture' => 'sec.1.2',
      ],
      '0.2, 1.2, secure' => [
        'site_patch_version' => '0.2',
        'expected_security_release' => ['1.2'],
        'expected_update_message_type' => static::UPDATE_AVAILABLE,
        'fixture' => 'sec.1.2',
      ],
      // Site on 2.0-rc2 which is a security release.
      '2.0-rc2, 0.2 1.2' => [
        'site_patch_version' => '2.0-rc2',
        'expected_security_releases' => [],
        'expected_update_message_type' => static::UPDATE_NONE,
        'fixture' => 'sec.0.2-rc2',
      ],
    ];
    $pre_releases = [
      '2.0-alpha1',
      '2.0-alpha2',
      '2.0-beta1',
      '2.0-beta2',
      '2.0-rc1',
      '2.0-rc2',
    ];

    // If the site is on an alpha/beta/RC of an upcoming minor and none of the
    // alpha/beta/RC versions are marked insecure, no security update should be
    // required.
    foreach ($pre_releases as $pre_release) {
      $test_cases["Pre-release:$pre_release, no security update"] = [
        'site_patch_version' => $pre_release,
        'expected_security_releases' => [],
        'expected_update_message_type' => $pre_release === '2.0-rc2' ? static::UPDATE_NONE : static::UPDATE_AVAILABLE,
        'fixture' => 'sec.0.2-rc2-b',
      ];
    }

    // @todo In https://www.drupal.org/node/2865920 add test cases:
    //   - For all pre-releases for 8.2.0 except 8.2.0-rc2 using the
    //     'sec.0.2-rc2' fixture to ensure that 8.2.0-rc2 is the only security
    //     update.
    //   - For 8.1.0 using fixture 'sec.0.2-rc2' to ensure that only security
    //     updates are 8.1.2 and 8.2.0-rc2.
    return $test_cases;
  }


  /**
   * Checks the messages at admin/modules when the site is up to date.
   */
  public function testModulePageUpToDate() {
    $this->setProjectInfo('8.0.0');
    // Instead of using refreshUpdateStatus(), set these manually.
    $this->config('update.settings')
      ->set('fetch.url', Url::fromRoute('update_test.update_test')->setAbsolute()->toString())
      ->save();
    $this->config('update_test.settings')
      ->set('xml_map', [$this->updateProject => '0.0'])
      ->save();

    $this->drupalGet('admin/reports/updates');
    $this->clickLink(t('Check manually'));
    $this->checkForMetaRefresh();
    $this->assertUpdateTableTextContains(t('Checked available update data for one project.'));
    $this->drupalGet('admin/modules');
    $this->assertUpdateTableTextNotContains("There are updates available for your version of {$this->projectTitle}.");
    $this->assertUpdateTableTextNotContains("There is a security update available for your version of {$this->projectTitle}.");
  }

  /**
   * Tests messages when a project release is unpublished.
   *
   * This test confirms that revoked messages are displayed regardless of
   * whether the installed version is in a supported branch or not. This test
   * relies on 2 test XML fixtures that are identical except for the
   * 'supported_branches' value:
   * - drupal.1.0.xml
   *    'supported_branches' is '8.0.,8.1.'.
   * - drupal.1.0-unsupported.xml
   *    'supported_branches' is '8.1.'.
   * They both have an '8.0.2' release that is unpublished and an '8.1.0'
   * release that is published and is the expected update.
   */
  public function testRevokedRelease() {
    foreach (['1.0', '1.0-unsupported'] as $fixture) {
      $this->setProjectInfo('8.0.2');
      $this->refreshUpdateStatus([$this->updateProject => $fixture]);
      $this->standardTests();
      $this->confirmRevokedStatus('8.0.2', '8.1.0', 'Recommended version:');
    }
  }

  /**
   * Tests messages when a project release is marked unsupported.
   *
   * This test confirms unsupported messages are displayed regardless of whether
   * the installed version is in a supported branch or not. This test relies on
   * 2 test XML fixtures that are identical except for the 'supported_branches'
   * value:
   * - drupal.1.0.xml
   *    'supported_branches' is '8.0.,8.1.'.
   * - drupal.1.0-unsupported.xml
   *    'supported_branches' is '8.1.'.
   * They both have an '8.0.3' release that that has the 'Release type' value of
   * 'unsupported' and an '8.1.0' release that has the 'Release type' value of
   * 'supported' and is the expected update.
   */
  public function testUnsupportedRelease() {
    foreach (['1.0', '1.0-unsupported'] as $fixture) {
      $this->setProjectInfo('8.0.3');
      $this->refreshUpdateStatus([$this->updateProject => $fixture]);
      $this->standardTests();
      $this->confirmUnsupportedStatus('8.0.3', '8.1.0', 'Recommended version:');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function assertVersionUpdateLinks($label, $version, $download_version = NULL) {
    // Test XML files for Drupal core use '-' in the version number for the
    // download link.
    $download_version = str_replace('.', '-', $version);
    parent::assertVersionUpdateLinks($label, $version, $download_version);
  }

  /**
   * @inheritDoc
   */
  protected function refreshUpdateStatus($xml_map, $url = 'update-test') {
    if (!isset($xml_map['drupal'])) {
      $xml_map['drupal'] = '0.0';
    }
    parent::refreshUpdateStatus($xml_map, $url); // TODO: Change the autogenerated stub
  }

  protected function setProjectInfo($version) {
    $system_info = [
      $this->updateProject => [
        'project' => $this->updateProject,
        'version' => $version,
        'hidden' => FALSE,
      ],
    ];
    if ($this->updateProject !== 'drupal') {
      $system_info['drupal'] = [
        'project' => 'drupal',
        'version' => $version,
        'hidden' => FALSE,
      ];
    }
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
  }



}

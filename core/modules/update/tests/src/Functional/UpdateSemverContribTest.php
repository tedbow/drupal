<?php

namespace Drupal\Tests\update\Functional;

/**
 * Tests the Update Manager module with a contrib module with semver versions.
 *
 * @group update
 */
class UpdateSemverContribTest extends UpdateSemverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $updateTableLocator = 'table.update:nth-of-type(2)';

  /**
   * {@inheritdoc}
   */
  protected $updateProject = 'semver_test';

  /**
   * {@inheritdoc}
   */
  protected $projectTitle = 'Semver Test';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['semver_test'];

  /**
   * {@inheritdoc}
   */
  protected function setProjectInstalledVersion($version) {
    $system_info = [
      $this->updateProject => [
        'project' => $this->updateProject,
        'version' => $version,
        'hidden' => FALSE,
      ],
      // Ensure Drupal core on the same version for all test runs.
      'drupal' => [
        'project' => 'drupal',
        'version' => '8.0.0',
        'hidden' => FALSE,
      ],
    ];
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
  }

  /**
   * Test updates from legacy versions to the semver versions.
   */
  public function testUpdatesLegacyToSemver() {
    foreach ([7, 8] as $legacy_major) {
      $semver_major = $legacy_major + 1;
      $installed_versions = [
        "8.x-$legacy_major.0-alpha1",
        "8.x-$legacy_major.0-beta1",
        "8.x-$legacy_major.0",
        "8.x-$legacy_major.1-alpha1",
        "8.x-$legacy_major.1-beta1",
        "8.x-$legacy_major.1",
      ];
      foreach ($installed_versions as $install_version) {
        $this->setProjectInstalledVersion($install_version);
        $fixture = $legacy_major === 7 ? '1.0' : '9.1.0';
        $this->refreshUpdateStatus([$this->updateProject => $fixture]);
        $this->assertUpdateTableTextNotContains(t('Security update required!'));
        $this->assertSession()->elementTextContains('css', $this->updateTableLocator . " .project-update__title", $install_version);
        // All installed versions should indicate that there is an update
        // available for the next major version of the module.
        // '$legacy_major.1.0' is shown for the next major version because it is
        // the latest full release for that major.
        // @todo Determine if both 8.0.0 and 8.0.1 should be expected as "Also
        //   available" releases in https://www.drupal.org/project/node/3100115.
        $this->assertVersionUpdateLinks('Also available:', "$semver_major.1.0");
        if ($install_version === "8.x-$legacy_major.1") {
          $this->assertUpdateTableTextContains('Up to date');
          $this->assertUpdateTableTextNotContains(t('Update available'));
        }
        else {
          $this->assertUpdateTableTextNotContains('Up to date');
          $this->assertUpdateTableTextContains(t('Update available'));
          // All installed versions besides 8.x-$legacy_major.1 should recommend
          // 8.x-$legacy_major.1 because it is the latest full release for the
          // major.
          $this->assertVersionUpdateLinks('Recommended version:', "8.x-$legacy_major.1");
        }
      }
    }
  }

}

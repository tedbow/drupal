<?php

namespace Drupal\Tests\update\Functional;

/**
 * Tests the Update Manager module with a contrib module with semantic versions.
 *
 * @group update
 */
class UpdateSemanticContribTest extends UpdateSemanticTestBase {

  /**
   * {@inheritdoc}
   */
  protected $updateTableLocator = 'table.update:nth-of-type(2)';

  /**
   * {@inheritdoc}
   */
  protected $updateProject = 'semantic_test';

  /**
   * {@inheritdoc}
   */
  protected $projectTitle = 'Semantic Test';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['semantic_test'];

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
    ];
    // Ensure Drupal core on the same version for all test runs.
    if ($this->updateProject !== 'drupal') {
      $system_info['drupal'] = [
        'project' => 'drupal',
        'version' => '8.0.0',
        'hidden' => FALSE,
      ];
    }
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
  }

  /**
   * Test updates from legacy versions to the semantic versions.
   */
  public function testUpdatesLegacyToSemantic() {
    $install_versions = [
      '8.x-7.0-alpha1',
      '8.x-7.0-beta1',
      '8.x-7.0',
      '8.x-7.1-alpha1',
      '8.x-7.1-beta1',
      '8.x-7.1',
    ];
    $this->refreshUpdateStatus([$this->updateProject => '1.0']);
    foreach ($install_versions as $install_version) {
      $this->setProjectInstalledVersion($install_version);
      $this->drupalGet('admin/reports/updates');
      $this->clickLink(t('Check manually'));
      $this->checkForMetaRefresh();
      $this->assertUpdateTableTextNotContains(t('Security update required!'));
      $this->assertSession()->elementTextContains('css', $this->updateTableLocator . " .project-update__title", $install_version);
      // All installed versions should indicate that there is update available
      // for the next major version of the module.
      $this->assertVersionUpdateLinks('Also available:', '8.1.0');
      if ($install_version === '8.x-7.1') {
        $this->assertUpdateTableTextContains('Up to date');
        $this->assertUpdateTableTextNotContains(t('Update available'));
      }
      else {
        $this->assertUpdateTableTextNotContains('Up to date');
        $this->assertUpdateTableTextContains(t('Update available'));
        // All installed versions besides 8.x-7.1 should recommend 8.x-7.1
        // because it is the latest full release for the major.
        $this->assertVersionUpdateLinks('Recommended version:', '8.x-7.1');
      }
    }
  }

}

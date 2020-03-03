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
  protected function standardTests() {
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
      $this->setProjectInfo($install_version);
      $this->drupalGet('admin/reports/updates');
      $this->clickLink(t('Check manually'));
      $this->checkForMetaRefresh();
      $this->assertUpdateTableTextNotContains(t('Security update required!'));
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

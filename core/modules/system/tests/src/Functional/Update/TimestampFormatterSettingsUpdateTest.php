<?php

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update of timestamp formatter settings in entity view displays.
 *
 * @group system
 */
class TimestampFormatterSettingsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8-rc1.bare.standard.php.gz',
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.timestamp-formatter-settings-2921810.php',
    ];
  }

  /**
   * Tests system_post_update_timestamp_formatter().
   *
   * @see system_post_update_timestamp_formatter()
   */
  public function testPostUpdateTimestampFormatter() {
    $config_factory = \Drupal::configFactory();
    $name = 'core.entity_view_display.node.page.default';
    $trail = 'content.field_foo.settings';

    // Check that 'tooltip' and 'timeago' are missing before update.
    $settings = $config_factory->get($name)->get($trail);
    $this->assertArrayNotHasKey('tooltip', $settings);
    $this->assertArrayNotHasKey('timeago', $settings);

    $this->runUpdates();

    // Check that 'tooltip' and 'timeago' were created after update.
    $settings = $config_factory->get($name)->get($trail);
    $this->assertArrayHasKey('tooltip', $settings);
    $this->assertArrayHasKey('timeago', $settings);
  }

}

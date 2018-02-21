<?php

namespace Drupal\FunctionalJavascriptTests\Core\Field;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests the 'timestamp' formatter when is used with 'time ago' setting.
 *
 * @group Field
 */
class TimestampFormatterWithTimeagoTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'time_field',
      'type' => 'timestamp',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'time_field',
      'label' => $this->randomString(),
    ])->save();
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $display->setComponent('time_field', [
        'type' => 'timestamp',
        'settings' => [
          'timeago' => [
            'enabled' => TRUE,
            'future_format' => '@interval hence',
            'past_format' => '@interval ago',
            'granularity' => 2,
            'refresh' => 1,
          ],
        ],
      ])->setStatus(TRUE)->save();

    $account = $this->createUser([
      'view test entity',
      'administer entity_test content',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests the 'timestamp' formatter when is used with 'time ago' setting.
   */
  public function testTimestampFormatterWithTimeago() {
    $entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
      'time_field' => $this->container->get('datetime.time')->getRequestTime(),
    ]);
    $entity->save();

    $this->drupalGet($entity->toUrl());

    $this->assertJsCondition("jQuery('time:contains(\"5 seconds\")').length > 0");
  }

}

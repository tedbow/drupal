<?php

namespace Drupal\Tests\layout_builder\Unit;

/**
 * Trait for testing layout_builder classes that support third party settings.
 */
trait ThirdPartySettingsTestTrait {

  /**
   * The initial third party settings.
   *
   * @var array
   */
  protected $initialThirdPartySettings = [
    'bad_judgement' => ['blink_speed' => 'fast', 'spin_direction' => 'clockwise'],
    'hunt_and_peck' => ['delay' => '300ms'],
  ];

  /**
   * @covers ::getThirdPartySettings
   */
  public function testGetThirdPartySettings() {
    $object = $this->getThirdPartySettingsObject();
    $this->assertSame(['blink_speed' => 'fast', 'spin_direction' => 'clockwise'], $object->getThirdPartySettings('bad_judgement'));
    $this->assertSame(['delay' => '300ms'], $object->getThirdPartySettings('hunt_and_peck'));
    $this->assertSame([], $object->getThirdPartySettings('non_existing_module'));
  }

  /**
   * @covers ::getThirdPartySetting
   */
  public function testGetThirdPartySetting() {
    $object = $this->getThirdPartySettingsObject();
    $this->assertSame('fast', $object->getThirdPartySetting('bad_judgement', 'blink_speed'));
    $this->assertSame('300ms', $object->getThirdPartySetting('hunt_and_peck', 'delay'));
    $this->assertSame(NULL, $object->getThirdPartySetting('hunt_and_peck', 'non_existing_key'));
    $this->assertSame(NULL, $object->getThirdPartySetting('non_existing_module', 'non_existing_key'));
  }

  /**
   * @covers ::setThirdPartySetting
   * @depends testGetThirdPartySetting
   */
  public function testSetThirdPartySetting() {
    $object = $this->getThirdPartySettingsObject();
    $object->setThirdPartySetting('bad_judgement', 'blink_speed', 'super fast');
    $this->assertSame(
      [
        'blink_speed' => 'super fast',
        'spin_direction' => 'clockwise',
      ],
      $object->getThirdPartySettings('bad_judgement')
    );
    $object->setThirdPartySetting('bad_judgement', 'new_setting', 'new_value');
    $this->assertSame(
      [
        'blink_speed' => 'super fast',
        'spin_direction' => 'clockwise',
        'new_setting' => 'new_value',
      ],
      $object->getThirdPartySettings('bad_judgement')
    );
    $object->setThirdPartySetting('new_module', 'new_setting', 'new_value');
    $this->assertSame(['new_setting' => 'new_value'], $object->getThirdPartySettings('new_module'));
  }

  /**
   * @covers ::unsetThirdPartySetting
   * @depends testGetThirdPartySettings
   */
  public function testUnsetThirdPartySetting() {
    $object = $this->getThirdPartySettingsObject();
    $object->unsetThirdPartySetting('bad_judgement', 'blink_speed');
    $this->assertSame(['spin_direction' => 'clockwise'], $object->getThirdPartySettings('bad_judgement'));
    $object->unsetThirdPartySetting('hunt_and_peck', 'delay');
    $this->assertSame([], $object->getThirdPartySettings('hunt_and_peck'));
    $object->unsetThirdPartySetting('bad_judgement', 'non_existing_key');
    $object->unsetThirdPartySetting('non_existing_module', 'non_existing_key');
  }

  /**
   * @covers ::getThirdPartyProviders
   * @depends testUnsetThirdPartySetting
   */
  public function testGetThirdPartyProviders() {
    $object = $this->getThirdPartySettingsObject();
    $this->assertSame(['bad_judgement', 'hunt_and_peck'], $object->getThirdPartyProviders());
    $object->unsetThirdPartySetting('hunt_and_peck', 'delay');
    $this->assertSame(['bad_judgement'], $object->getThirdPartyProviders());
  }

  /**
   * Gets the object that supports third party settings.
   *
   * @return \Drupal\Core\Config\Entity\ThirdPartySettingsInterface
   *   The object of the class to test.
   */
  abstract protected function getThirdPartySettingsObject();

}

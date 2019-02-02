<?php

namespace Drupal\layout_builder;

/**
 * Implementation of \Drupal\Core\Config\Entity\ThirdPartySettingsInterface.
 */
trait ThirdPartySettingsTrait {

  /**
   * Third party entity settings.
   *
   * An array of key/value pairs keyed by provider.
   *
   * @var array
   */
  protected $third_party_settings = [];

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySetting($module, $key, $value) {
    $this->third_party_settings[$module][$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartySetting($module, $key, $default = NULL) {
    if (isset($this->third_party_settings[$module][$key])) {
      return $this->third_party_settings[$module][$key];
    }
    else {
      return $default;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartysettings($module) {
    return isset($this->third_party_settings[$module]) ? $this->third_party_settings[$module] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function unsetThirdPartySetting($module, $key) {
    unset($this->third_party_settings[$module][$key]);
    // If the third party is no longer storing any information, completely
    // remove the array holding the settings for this module.
    if (empty($this->third_party_settings[$module])) {
      unset($this->third_party_settings[$module]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartyProviders() {
    return array_keys($this->third_party_settings);
  }

}

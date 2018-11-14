<?php

namespace Drupal\layout_builder_test\Plugin\SectionStorage;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Provides a test override of section storage.
 */
class TestOverridesSectionStorage extends OverridesSectionStorage {

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {
    \Drupal::state()->set('layout_builder_test_storage', [
      $this->getPluginDefinition()->get('weight'),
      $this->getContextValue('view_mode'),
    ]);
    return parent::getSectionList();
  }

}

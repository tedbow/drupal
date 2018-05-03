<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides an access check for the Layout Builder defaults.
 *
 * @internal
 */
class LayoutDefaultsEnabledAccessCheck implements AccessInterface {

  /**
   * Checks routing access to the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage) {
    $defaults_section_storage = NULL;
    if ($section_storage instanceof DefaultsSectionStorageInterface) {
      $defaults_section_storage = $section_storage;
    }
    elseif ($section_storage instanceof OverridesSectionStorageInterface) {
      $defaults_section_storage = $section_storage->getDefaultSectionStorage();
    }
    if ($defaults_section_storage) {
      $access = AccessResult::allowedIf($defaults_section_storage->isEnabled());
      $access->addCacheableDependency($defaults_section_storage);
    }
    else {
      $access = AccessResult::forbidden();
    }
    return $access->addCacheableDependency($section_storage);
  }

}

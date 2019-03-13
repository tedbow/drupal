<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TranslatableSectionStorageInterface;

/**
 * Provides an access check for the Layout Builder translations.
 *
 * When accessing the layout builder for a translation only translating labels
 * and inline blocks are supported.
 *
 * @internal
 */
class LayoutBuilderTranslationAccessCheck implements AccessInterface {

  /**
   * Checks routing access to the default translation only layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage) {
    $access = AccessResult::allowedIf(!($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation()));
    if ($access instanceof RefinableCacheableDependencyInterface) {
      $access->addCacheableDependency($section_storage);
    }
    return $access;
  }
}

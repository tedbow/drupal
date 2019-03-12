<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TranslatableOverridesSectionStorageInterface;
use Drupal\migrate\Plugin\migrate\process\Route;

/**
 * Provides an access check for the Layout Builder defaults.
 *
 * @internal
 */
class LayoutBuilderTranslationAccessCheck implements AccessInterface {
  /**
   * Checks routing access to the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage) {
    $access = AccessResult::allowedIf(!($section_storage instanceof TranslatableOverridesSectionStorageInterface && !$section_storage->isDefaultTranslation()));
    if ($access instanceof RefinableCacheableDependencyInterface) {
      $access->addCacheableDependency($section_storage);
    }
    return $access;
  }
}

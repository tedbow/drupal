<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TranslatableSectionStorageInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access check for the Layout Builder translations.
 *
 * @ingroup layout_builder_access
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutBuilderTranslationAccessCheck implements AccessInterface {

  /**
   * Checks routing access to the default translation only layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage, Route $route) {
    $translation_type = $route->getRequirement('_layout_builder_translation_access');
    if ($translation_type === 'untranslated') {
      $access = AccessResult::allowedIf(!$section_storage instanceof TranslatableSectionStorageInterface || $section_storage->isDefaultTranslation());
    }
    elseif ($translation_type === 'translated') {
      $access = AccessResult::allowedIf($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation());
    }
    else {
      throw new \UnexpectedValueException("Unexpected _layout_builder_translation_access route requirement: $translation_type");
    }
    $access->addCacheableDependency($section_storage);
    return $access;
  }

}

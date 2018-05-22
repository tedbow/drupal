<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\tester\Entity\InlineBlockInterface;

/**
 * Defines the storage handler class for Inline block entities.
 *
 * This extends the base storage class, adding required special handling for
 * Inline block entities.
 *
 * @ingroup tester
 */
interface InlineBlockStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Inline block revision IDs for a specific Inline block.
   *
   * @param \Drupal\tester\Entity\InlineBlockInterface $entity
   *   The Inline block entity.
   *
   * @return int[]
   *   Inline block revision IDs (in ascending order).
   */
  public function revisionIds(InlineBlockInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Inline block author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Inline block revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\tester\Entity\InlineBlockInterface $entity
   *   The Inline block entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(InlineBlockInterface $entity);

  /**
   * Unsets the language for all Inline block with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}

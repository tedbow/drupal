<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
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
class InlineBlockStorage extends SqlContentEntityStorage implements InlineBlockStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(InlineBlockInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {inline_block_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {inline_block_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(InlineBlockInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {inline_block_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('inline_block_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}

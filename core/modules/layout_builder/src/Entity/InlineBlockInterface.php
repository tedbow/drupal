<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Inline block entities.
 *
 * @ingroup layout_builder
 */
interface InlineBlockInterface extends ContentEntityInterface, RevisionLogInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Inline block name.
   *
   * @return string
   *   Name of the Inline block.
   */
  public function getName();

  /**
   * Sets the Inline block name.
   *
   * @param string $name
   *   The Inline block name.
   *
   * @return \Drupal\layout_builder\Entity\InlineBlockInterface
   *   The called Inline block entity.
   */
  public function setName($name);

  /**
   * Gets the Inline block revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Inline block revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\layout_builder\Entity\InlineBlockInterface
   *   The called Inline block entity.
   */
  public function setRevisionUserId($uid);

}

<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Inline block entities.
 *
 * @ingroup layout_builder
 */
interface InlineBlockInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

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
   * Gets the Inline block creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Inline block.
   */
  public function getCreatedTime();

  /**
   * Sets the Inline block creation timestamp.
   *
   * @param int $timestamp
   *   The Inline block creation timestamp.
   *
   * @return \Drupal\layout_builder\Entity\InlineBlockInterface
   *   The called Inline block entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Inline block published status indicator.
   *
   * Unpublished Inline block are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Inline block is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Inline block.
   *
   * @param bool $published
   *   TRUE to set this Inline block to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\layout_builder\Entity\InlineBlockInterface
   *   The called Inline block entity.
   */
  public function setPublished($published);

  /**
   * Gets the Inline block revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Inline block revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\layout_builder\Entity\InlineBlockInterface
   *   The called Inline block entity.
   */
  public function setRevisionCreationTime($timestamp);

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

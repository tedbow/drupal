<?php

namespace Drupal\block_content;

/**
 * Defines events for the block_content module.
 *
 * @see \Drupal\block_content\Event\BlockContentGetDependenciesEvent
 */
final class BlockContentEvents {

  /**
   * Name of the event when getting the dependencies of a non-reusable block.
   *
   * This event allows modules to set the dependencies of non-reusable block if
   * \Drupal\block_content\Entity\BlockContent::setAccessDependencies has not
   * been called.
   *
   * @Event
   *
   * @see \Drupal\block_content\Event\BlockContentGetDependenciesEvent
   * @see \Drupal\block_content\BlockContentAccessControlHandler::checkAccess()
   *
   * @var string
   */
  const BLOCK_CONTENT_GET_DEPENDENCIES = 'block_content.get_dependencies';

}

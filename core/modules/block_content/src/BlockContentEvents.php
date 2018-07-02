<?php

namespace Drupal\block_content;

/**
 * Defines events for the block_content module.
 *
 * @see \Drupal\block_content\Event\BlockContentGetDependencyEvent
 */
final class BlockContentEvents {

  /**
   * Name of the event when getting the dependency of a non-reusable block.
   *
   * This event allows modules to set a dependency of non-reusable block if
   * \Drupal\Core\Access\AccessDependentTrait::getAccessDependency has not been
   * called.
   *
   * @Event
   *
   * @see \Drupal\block_content\Event\BlockContentGetDependencyEvent
   * @see \Drupal\block_content\BlockContentAccessControlHandler::checkAccess()
   *
   * @var string
   */
  const INLINE_BLOCK_GET_DEPENDENCY = 'block_content.get_dependency';

}

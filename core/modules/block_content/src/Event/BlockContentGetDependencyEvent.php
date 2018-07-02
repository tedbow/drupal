<?php

namespace Drupal\block_content\Event;

use Drupal\block_content\BlockContentInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Block content event to allow setting an access dependency.
 */
class BlockContentGetDependencyEvent extends Event {

  /**
   * The block content entity.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $blockContent;

  /**
   * The dependency.
   *
   * @var \Drupal\Core\Access\AccessibleInterface
   */
  protected $dependency;

  /**
   * BlockContentGetDependencyEvent constructor.
   *
   * @param \Drupal\block_content\BlockContentInterface $blockContent
   *   The block content entity.
   */
  public function __construct(BlockContentInterface $blockContent) {
    $this->blockContent = $blockContent;
  }

  /**
   * Gets the block content entity.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   The block content entity.
   */
  public function getBlockContent() {
    return $this->blockContent;
  }

}

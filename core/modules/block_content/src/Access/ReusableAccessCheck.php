<?php

namespace Drupal\block_content\Access;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Route access check for 'block_content' entities based on 'reusable' field.
 */
class ReusableAccessCheck implements AccessInterface {

  /**
   * Checks routing access to a block.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The block content entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(BlockContentInterface $block_content) {
    $access = AccessResult::allowedIf($block_content->isReusable());
    return $access->addCacheableDependency($block_content);
  }

}

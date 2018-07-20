<?php

namespace Drupal\block_content\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides specific selection control for the block_content entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:block_content",
 *   label = @Translation("Custom block selection"),
 *   entity_types = {"block_content"},
 *   group = "default",
 *   weight = 1
 * )
 */
class BlockContentSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    // Only reusable blocks should be able to be referenced.
    $query->condition('reusable', TRUE);
    return $query;
  }

}

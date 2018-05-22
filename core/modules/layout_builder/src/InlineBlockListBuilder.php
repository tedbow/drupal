<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Inline block entities.
 *
 * @ingroup layout_builder
 */
class InlineBlockListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Inline block ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\layout_builder\Entity\InlineBlock */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.inline_block.edit_form',
      ['inline_block' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}

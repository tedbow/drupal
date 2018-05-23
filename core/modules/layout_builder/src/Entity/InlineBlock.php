<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Inline block entity.
 *
 * @ingroup layout_builder
 *
 * @ContentEntityType(
 *   id = "inline_block",
 *   label = @Translation("Inline block"),
 *   bundle_label = @Translation("Inline block type"),
 *   handlers = {
 *     "storage" = "Drupal\layout_builder\InlineBlockStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *     "access" = "Drupal\layout_builder\InlineBlockAccessControlHandler",
 *   },
 *   base_table = "inline_block",
 *   revision_table = "inline_block_revision",
 *   revision_data_table = "inline_block_field_revision",
 *   admin_permission = "administer inline block entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/inline_block/{inline_block}",
 *   },
 *   bundle_entity_type = "inline_block_type",
 *   field_ui_base_route = "entity.inline_block_type.edit_form"
 * )
 */
class InlineBlock extends RevisionableContentEntityBase implements InlineBlockInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // parent_entity_type and parent_entity_id are only for usage tracking.
    $fields['parent_entity_type'] = BaseFieldDefinition::create('string')
      // @todo Also no need to revision since can't change?
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['parent_entity_id'] = BaseFieldDefinition::create('string')
      // @todo Also no need to revision since can't change?
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}

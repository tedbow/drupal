<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Inline block type entity.
 *
 * @ConfigEntityType(
 *   id = "inline_block_type",
 *   label = @Translation("Inline block type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\layout_builder\InlineBlockTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\layout_builder\Form\InlineBlockTypeForm",
 *       "edit" = "Drupal\layout_builder\Form\InlineBlockTypeForm",
 *       "delete" = "Drupal\layout_builder\Form\InlineBlockTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\layout_builder\InlineBlockTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "inline_block_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "inline_block",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/inline_block_type/{inline_block_type}",
 *     "add-form" = "/admin/structure/inline_block_type/add",
 *     "edit-form" = "/admin/structure/inline_block_type/{inline_block_type}/edit",
 *     "delete-form" = "/admin/structure/inline_block_type/{inline_block_type}/delete",
 *     "collection" = "/admin/structure/inline_block_type"
 *   }
 * )
 */
class InlineBlockType extends ConfigEntityBundleBase implements InlineBlockTypeInterface {

  /**
   * The Inline block type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Inline block type label.
   *
   * @var string
   */
  protected $label;

}

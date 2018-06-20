<?php

namespace Drupal\block_content\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Used for creating 'block_content' views with the wizard.
 *
 * @ViewsWizard(
 *   id = "block_content",
 *   base_table = "block_content_field_data",
 *   title = @Translation("Custom Block"),
 * )
 */
class BlockContent extends WizardPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    $filters = parent::getFilters();
    $filters['has_parent'] = [
      'id' => 'has_parent',
      'plugin_id' => 'boolean_string',
      'table' => $this->base_table,
      'field' => 'has_parent',
      'operator' => '=',
      'value' => '0',
      'entity_type' => $this->entityTypeId,
      'entity_field' => 'parent_entity_id',
    ];
    return $filters;
  }

}

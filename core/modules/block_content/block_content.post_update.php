<?php

/**
 * @file
 * Post update functions for Custom Block.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Adds 'has_parent' filter to Custom Block views.
 */
function block_content_post_update_add_views_parent_filter(&$sandbox = NULL) {
  $data_table = \Drupal::entityTypeManager()
    ->getDefinition('block_content')
    ->getDataTable();

  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function ($view) use ($data_table) {
    /** @var \Drupal\views\ViewEntityInterface $view */
    if ($view->get('base_table') != $data_table) {
      return FALSE;
    }
    $save_view = FALSE;
    $displays = $view->get('display');
    foreach ($displays as $display_name => &$display) {
      // Update the default display and displays that have overridden filters.
      if (!isset($display['display_options']['filters']['has_parent']) &&
        ($display_name === 'default' || isset($display['display_options']['filters']))) {
        $display['display_options']['filters']['has_parent'] = [
          'id' => 'has_parent',
          'plugin_id' => 'boolean_string',
          'table' => $data_table,
          'field' => 'has_parent',
          'value' => '0',
          'entity_type' => 'block_content',
          'entity_field' => 'parent_entity_type',
        ];
        $save_view = TRUE;
      }
    }
    if ($save_view) {
      $view->set('display', $displays);
    }
    return $save_view;
  });
}

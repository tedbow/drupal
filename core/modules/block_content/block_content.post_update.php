<?php

/**
 * @file
 * Post update functions for Custom Block.
 */

/**
 * Adds 'reusable filter to a Custom Block views.
 */
function block_content_post_update_add_views_reusable_filter() {
  $config_factory = \Drupal::configFactory();
  $data_table = \Drupal::entityTypeManager()
    ->getDefinition('block_content')
    ->getDataTable();

  foreach ($config_factory->listAll('views.view.') as $view_config_name) {
    $view = $config_factory->getEditable($view_config_name);
    if ($view->get('base_table') != $data_table) {
      continue;
    }
    foreach ($view->get('display') as $display_name => $display) {
      // Update the default display and displays that have overridden filters.
      if (!isset($display['display_options']['filters']['reusable']) &&
        ($display_name === 'default' || isset($display['display_options']['filters']))) {
        // Save off the base part of the config path we are updating.
        $base = "display.$display_name.display_options.filters.reusable";
        $view->set("$base.id", 'reusable')
          ->set("$base.plugin_id", 'boolean')
          ->set("$base.table", $data_table)
          ->set("$base.field", "reusable")
          ->set("$base.value", "1")
          ->set("$base.entity_type", "block_content")
          ->set("$base.entity_field", "reusable");
      }
    }
    $view->save();
  }
}

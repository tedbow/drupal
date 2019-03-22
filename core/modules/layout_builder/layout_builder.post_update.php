<?php

/**
 * @file
 * Post update functions for Layout Builder.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\TempStoreIdentifierInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Entity\Sql\DefaultTableMapping;

/**
 * Rebuild plugin dependencies for all entity view displays.
 */
function layout_builder_post_update_rebuild_plugin_dependencies(&$sandbox = NULL) {
  $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
  if (!isset($sandbox['ids'])) {
    $sandbox['ids'] = $storage->getQuery()->accessCheck(FALSE)->execute();
    $sandbox['count'] = count($sandbox['ids']);
  }

  for ($i = 0; $i < 10 && count($sandbox['ids']); $i++) {
    $id = array_shift($sandbox['ids']);
    if ($display = $storage->load($id)) {
      $display->save();
    }
  }

  $sandbox['#finished'] = empty($sandbox['ids']) ? 1 : ($sandbox['count'] - count($sandbox['ids'])) / $sandbox['count'];
}

/**
 * Ensure all extra fields are properly stored on entity view displays.
 *
 * Previously
 * \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::setComponent()
 * was not correctly setting the configuration for extra fields. This function
 * calls setComponent() for all extra field components to ensure the updated
 * logic is invoked on all extra fields to correct the settings.
 */
function layout_builder_post_update_add_extra_fields(&$sandbox = NULL) {
  $entity_field_manager = \Drupal::service('entity_field.manager');
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (LayoutEntityDisplayInterface $display) use ($entity_field_manager) {
    if (!$display->isLayoutBuilderEnabled()) {
      return FALSE;
    }

    $extra_fields = $entity_field_manager->getExtraFields($display->getTargetEntityTypeId(), $display->getTargetBundle());
    $components = $display->getComponents();
    // Sort the components to avoid them being reordered by setComponent().
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    $result = FALSE;
    foreach ($components as $name => $component) {
      if (isset($extra_fields['display'][$name])) {
        $display->setComponent($name, $component);
        $result = TRUE;
      }
    }
    return $result;
  });
}

/**
 * Clear caches due to changes to section storage annotation changes.
 */
function layout_builder_post_update_section_storage_context_definitions() {
  // Empty post-update hook.
}

/**
 * Clear caches due to changes to annotation changes to the Overrides plugin.
 */
function layout_builder_post_update_overrides_view_mode_annotation() {
  // Empty post-update hook.
}

/**
 * Clear caches due to routing changes for the new discard changes form.
 */
function layout_builder_post_update_cancel_link_to_discard_changes_form() {
  // Empty post-update hook.
}

/**
 * Clear caches due to the removal of the layout_is_rebuilding query string.
 */
function layout_builder_post_update_remove_layout_is_rebuilding() {
  // Empty post-update hook.
}

/**
 * Clear caches due to routing changes to move the Layout Builder UI to forms.
 */
function layout_builder_post_update_routing_entity_form() {
  // Empty post-update hook.
}

/**
 * Clear caches to discover new blank layout plugin.
 */
function layout_builder_post_update_discover_blank_layout_plugin() {
  // Empty post-update hook.
}

/**
 * Clear caches due to routing changes to changing the URLs for defaults.
 */
function layout_builder_post_update_routing_defaults() {
  // Empty post-update hook.
}

/**
 * Clear caches due to new link added to Layout Builder's contextual links.
 */
function layout_builder_post_update_discover_new_contextual_links() {
  // Empty post-update hook.
}

/**
 * Fix Layout Builder tempstore keys of existing entries.
 */
function layout_builder_post_update_fix_tempstore_keys() {
  /** @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager */
  $section_storage_manager = \Drupal::service('plugin.manager.layout_builder.section_storage');
  /** @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_factory */
  $key_value_factory = \Drupal::service('keyvalue.expirable');

  // Loop through each section storage type.
  foreach (array_keys($section_storage_manager->getDefinitions()) as $section_storage_type) {
    $key_value = $key_value_factory->get("tempstore.shared.layout_builder.section_storage.$section_storage_type");
    foreach ($key_value->getAll() as $key => $value) {
      $contexts = $section_storage_manager->loadEmpty($section_storage_type)->deriveContextsFromRoute($key, [], '', []);
      if ($section_storage = $section_storage_manager->load($section_storage_type, $contexts)) {

        // Some overrides were stored with an incorrect view mode value. Update
        // the view mode on the temporary section storage, if necessary.
        if ($section_storage_type === 'overrides') {
          $view_mode = $value->data['section_storage']->getContextValue('view_mode');
          $new_view_mode = $section_storage->getContextValue('view_mode');
          if ($view_mode !== $new_view_mode) {
            $value->data['section_storage']->setContextValue('view_mode', $new_view_mode);
            $key_value->set($key, $value);
          }
        }

        // The previous tempstore key names were exact matches with the section
        // storage ID. Attempt to load the corresponding section storage and
        // rename the tempstore entry if the section storage provides a more
        // granular tempstore key.
        if ($section_storage instanceof TempStoreIdentifierInterface) {
          $new_key = $section_storage->getTempstoreKey();
          if ($key !== $new_key) {
            if ($key_value->has($new_key)) {
              $key_value->delete($new_key);
            }
            $key_value->rename($key, $new_key);
          }
        }
      }
    }
  }
}

/**
 * Clear caches due to config schema additions.
 */
function layout_builder_post_update_section_third_party_settings_schema() {
  // Empty post-update hook.
}

/**
 * @todo.
 */
function layout_builder_post_update_make_layout_untranslatable2() {
  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  $field_map = $field_manager->getFieldMap();
  $a = 'b';
  foreach ($field_map as $entity_type_id => $field_infos) {
    $entity_type_has_translated_layouts = FALSE;
    if (isset($field_infos['layout_builder__layout']['bundles'])) {
      foreach ($field_infos['layout_builder__layout']['bundles'] as $bundle) {
        if (_layout_builder_no_translated_layouts($entity_type_id, $bundle)) {
          $entity_type_has_translated_layouts = TRUE;

          $field_config = FieldConfig::loadByName($entity_type_id, $bundle, OverridesSectionStorage::FIELD_NAME);

          $field_config->setTranslatable(FALSE);
          $field_config->save();
        }
        else {
          $a = 'b';
        }

      }
      if ($entity_type_has_translated_layouts === FALSE) {
        $field_storage = FieldStorageConfig::loadByName($entity_type_id, OverridesSectionStorage::FIELD_NAME);
        $field_storage->setTranslatable(FALSE);
        $field_storage->save();
      }
    }
  }
}

function _layout_builder_no_translated_layouts($entity_type_id, $bundle) {
  $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
  $revision_key = $entity_type->getKey('revision');
  $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  if ($storage instanceof SqlContentEntityStorage) {
    $table_mapping = $storage->getTableMapping();
    // We are only able determine the field revision table using
    // DefaultTableMapping.
    // @todo Check for \Drupal\Core\Entity\Sql\TableMappingInterface in
    //    https://www.drupal.org/node/2955442.
    if ($table_mapping instanceof DefaultTableMapping) {
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $field */
      $fields = $field_manager->getFieldStorageDefinitions($entity_type_id);
      $field_storage = $fields[OverridesSectionStorage::FIELD_NAME];
      $revision_field_table = $table_mapping->getDedicatedRevisionTableName($field_storage);
      $revision_data_table = $table_mapping->getRevisionDataTable();
      $select = Drupal::database()->select($revision_data_table, 'r');
      $select->condition('r.default_langcode', 0);
      $select->innerJoin($revision_field_table, 'rf', "r.$revision_key = rf.revision_id");
      $select->condition('rf.bundle', $bundle);
      $select->isNotNull('rf.layout_builder__layout_section');
      $count = $select->countQuery()->execute()->fetchField();
      return empty($count);
    }
  }
  return FALSE;
}

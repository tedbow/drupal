<?php

/**
 * @file
 * Test fixture.
 */

use Drupal\Core\Database\Database;
use Drupal\layout_builder\Section;

$section_array_default = [
  'layout_id' => 'layout_onecol',
  'layout_settings' => [],
  'components' => [
    'some-uuid' => [
      'uuid' => 'some-uuid',
      'region' => 'content',
      'configuration' => [
        'id' => 'system_powered_by_block',
        'label' => 'This is in English',
        'provider' => 'system',
        'label_display' => 'visible',
      ],
      'additional' => [],
      'weight' => 0,
    ],
  ],
];
$section_array_translation = $section_array_default;
$section_array_translation['components']['some-uuid']['configuration']['label'] = 'This is in Spanish';

$connection = Database::getConnection();
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.content_settings.node.article',
    'data' => 'a:10:{s:4:"uuid";s:36:"450e592a-f451-4685-8f56-02b0f5107cb7";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:1:{i:0;s:17:"node.type.article";}s:6:"module";a:1:{i:0;s:19:"content_translation";}}s:20:"third_party_settings";a:1:{s:19:"content_translation";a:2:{s:7:"enabled";b:1;s:15:"bundle_settings";a:1:{s:26:"untranslatable_fields_hide";s:1:"0";}}}s:2:"id";s:12:"node.article";s:21:"target_entity_type_id";s:4:"node";s:13:"target_bundle";s:7:"article";s:16:"default_langcode";s:12:"site_default";s:18:"language_alterable";b:1;}',
  ])
  ->execute();

// Add Layout Builder sections to an existing entity view display.
$display = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.entity_view_display.node.article.default')
  ->execute()
  ->fetchField();
$display = unserialize($display);
$display['third_party_settings']['layout_builder']['sections'][] = $section_array_default;
$connection->update('config')
  ->fields([
    'data' => serialize($display),
    'collection' => '',
    'name' => 'core.entity_view_display.node.article.default',
  ])
  ->condition('collection', '')
  ->condition('name', 'core.entity_view_display.node.article.default')
  ->execute();

$values_en = [
  'bundle' => 'article',
  'deleted' => '0',
  'entity_id' => '1',
  'revision_id' => '2',
  'langcode' => 'en',
  'delta' => '0',
  'layout_builder__layout_section' => serialize(Section::fromArray($section_array_default)),
];
$values_es = $values_en;
$values_es['langcode'] = 'es';
$values_es['layout_builder__layout_section'] = serialize(Section::fromArray($section_array_translation));

// Add the layout data to the node.
$connection->insert('node__layout_builder__layout')
  ->fields(array_keys($values_en))
  ->values($values_en)
  ->execute();
$connection->insert('node_revision__layout_builder__layout')
  ->fields(array_keys($values_en))
  ->values($values_en)
  ->execute();
$connection->insert('node__layout_builder__layout')
  ->fields(array_keys($values_es))
  ->values($values_es)
  ->execute();
$connection->insert('node_revision__layout_builder__layout')
  ->fields(array_keys($values_es))
  ->values($values_es)
  ->execute();

$node_field_data = $connection->select('node_field_data')
  ->fields('node_field_data')
  ->condition('nid', '1')
  ->condition('vid', '2')
  ->execute()
  ->fetchAssoc();
$this->assertNotEmpty($node_field_data);
$node_field_data['title'] = 'Test Article - Spanish title';
$node_field_data['langcode'] = 'es';
$node_field_data['default_langcode'] = 0;
$node_field_data['revision_translation_affected'] = 1;
$node_field_data['content_translation_source'] = 'en';
$connection->insert('node_field_data')
  ->fields(array_keys($node_field_data))
  ->values($node_field_data)
  ->execute();

$node_field_revision = $connection->select('node_field_revision')
  ->fields('node_field_revision')
  ->condition('nid', '1')
  ->condition('vid', '2')
  ->execute()
  ->fetchAssoc();
$node_field_revision['title'] = 'Test Article - Spanish title';
$node_field_revision['langcode'] = 'es';
$node_field_revision['default_langcode'] = 0;
$node_field_revision['revision_translation_affected'] = 1;
$node_field_revision['content_translation_source'] = 'en';
$connection->insert('node_field_revision')
  ->fields(array_keys($node_field_revision))
  ->values($node_field_revision)
  ->execute();

<?php
$xml_path = 'core/modules/update/tests/modules/update_test/';
$xml_files = glob($xml_path . 'drupal.*.xml');
foreach ($xml_files as $xml_file) {
  $contents = file_get_contents($xml_file);
  $new_file_name = $xml_path . str_replace('drupal.', 'semantic_test.', basename($xml_file));
  $contents = transformContents($contents);
  //print "$new_file_name\n";
  file_put_contents($new_file_name, $contents);
}

function transformContents($contents) {
  $contents = str_replace('Drupal', 'Semantic Test', $contents);
  $contents = str_replace('<dc:creator>Semantic Test</dc:creator>', '<dc:creator>Drupal</dc:creator>', $contents);
  $contents = str_replace('drupal', 'semantic_test', $contents);
  $contents = str_replace('DRUPAL', 'SEMANTIC_TEST', $contents);
  return $contents;
}

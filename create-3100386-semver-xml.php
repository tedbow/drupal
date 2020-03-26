<?php
$xml_path = 'core/modules/update/tests/modules/update_test/';
$xml_files = glob($xml_path . 'drupal.*.xml');
// Exclude files only needed for testSecurityCoverageMessage().
$excluded_files = [
  'core/modules/update/tests/modules/update_test/semver_test.sec.2.0.xml',
  'core/modules/update/tests/modules/update_test/semver_test.sec.2.0_3.0-rc1.xml',
  'core/modules/update/tests/modules/update_test/semver_test.sec.2.0_9.0.0.xml',
  'core/modules/update/tests/modules/update_test/semver_test.sec.9.0.xml',
  'core/modules/update/tests/modules/update_test/semver_test.sec.9.9.0.xml',
];
foreach ($xml_files as $xml_file) {
  $contents = file_get_contents($xml_file);
  $new_file_name = $xml_path . str_replace('drupal.', 'semver_test.', basename($xml_file));
  if (in_array($new_file_name, $excluded_files)) {
    continue;
  }
  $contents = transformContents($contents);
  //print "$new_file_name\n";
  file_put_contents($new_file_name, $contents);
}

function transformContents($contents) {
  $contents = str_replace('Drupal', 'Semver Test', $contents);
  $contents = str_replace('<dc:creator>Semver Test</dc:creator>', '<dc:creator>Drupal</dc:creator>', $contents);
  $contents = str_replace('drupal', 'semver_test', $contents);
  $contents = str_replace('DRUPAL', 'SEMVER_TEST', $contents);

  $xml = new SimpleXMLElement($contents);
  if ($xml->releases != NULL) {
    // start date at Pi day 2020!
    $date = 1584195300;
    foreach ($xml->releases[0] as $release) {
      // Fix tags.
      if ($release->version != NULL && $release->tag != NULL) {
        $version = (string) $release->version;
        // Tag same as version except in the case dev snapshots.
        $release->tag = str_replace('-dev', '', $version);
      }
      else {
        throw new \UnexpectedValueException("no version or tag");
      }
      // Fix dates.
      if ($release->date != NULL) {
        $release->date = $date;
        // Minus about a  month for every release.
        $date -= 60 * 60 * 24 * 30;
      }
      else {
        throw new \UnexpectedValueException("no date");
      }

    }
  }

  $contents = (string) $xml->asXML();

  return $contents;
}

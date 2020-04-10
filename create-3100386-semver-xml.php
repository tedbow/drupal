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

  if (strpos($new_file_name, 'semver_test.1.0.xml') !== FALSE) {
    $contents = addReleases($contents);
    // Duplicate this xml to test 8.x-8 releases.
    $contents_legacy8 = convertLegacyMajor8($contents);
    $legacy8_file = str_replace('semver_test.1.0.xml', 'semver_test.9.1.0.xml', $new_file_name);
    file_put_contents($legacy8_file, $contents_legacy8);

    $contents_legacy_unsupported = str_replace('<supported_branches>8.x-7.,', '<supported_branches>', $contents);
    $legacy_unsupported_file = str_replace('semver_test.1.0.xml', 'semver_test.1.0-legacy-unsupported.xml', $new_file_name);
    file_put_contents($legacy_unsupported_file, $contents_legacy_unsupported);
  }
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


function  addReleases($contents) {
  $releases = <<<RELEASES
<?xml version="1.0" encoding="utf-8"?>
<releases>
<release>
    <name>Semver Test 8.x-7.1</name>
    <version>8.x-7.1</version>
    <tag>SEMVER_TEST-8-x-7-1</tag>
    <status>published</status>
    <release_link>http://example.com/semver_test-8-x-7-1-release</release_link>
    <download_link>http://example.com/semver_test-8-x-7-1.tar.gz</download_link>
    <date>1250424521</date>
    <terms>
      <term><name>Release type</name><value>New features</value></term>
      <term><name>Release type</name><value>Bug fixes</value></term>
    </terms>
  </release>
  <release>
    <name>Semver Test 8.x-7.1-beta1</name>
    <version>8.x-7.1-beta1</version>
    <tag>SEMVER_TEST-8-x-7-1-beta1</tag>
    <status>published</status>
    <release_link>http://example.com/semver_test-8-x-7-1-beta1-release</release_link>
    <download_link>http://example.com/semver_test-8-x-7-1-beta1.tar.gz</download_link>
    <date>1250424521</date>
    <terms>
      <term><name>Release type</name><value>New features</value></term>
      <term><name>Release type</name><value>Bug fixes</value></term>
    </terms>
  </release>
  <release>
    <name>Semver Test 8.x-7.1-alpha1</name>
    <version>8.x-7.1-alpha1</version>
    <tag>SEMVER_TEST-8-x-7-1-alpha1</tag>
    <status>published</status>
    <release_link>http://example.com/semver_test-8-x-7-1-alpha1-release</release_link>
    <download_link>http://example.com/semver_test-8-x-7-1-alpha1.tar.gz</download_link>
    <date>1250424521</date>
    <terms>
      <term><name>Release type</name><value>New features</value></term>
      <term><name>Release type</name><value>Bug fixes</value></term>
    </terms>
  </release>
  <release>
    <name>Semver Test 8.x-7.0</name>
    <version>8.x-7.0</version>
    <tag>SEMVER_TEST-8-x-7-0</tag>
    <status>published</status>
    <release_link>http://example.com/semver_test-8-x-7-0-release</release_link>
    <download_link>http://example.com/semver_test-8-x-7-0.tar.gz</download_link>
    <date>1250424521</date>
    <terms>
      <term><name>Release type</name><value>New features</value></term>
      <term><name>Release type</name><value>Bug fixes</value></term>
    </terms>
  </release>
  <release>
    <name>Semver Test 8.x-7.0-beta1</name>
    <version>8.x-7.0-beta1</version>
    <tag>SEMVER_TEST-8-x-7-0-beta1</tag>
    <status>published</status>
    <release_link>http://example.com/semver_test-8-x-7-0-beta1-release</release_link>
    <download_link>http://example.com/semver_test-8-x-7-0-beta1.tar.gz</download_link>
    <date>1250424521</date>
    <terms>
      <term><name>Release type</name><value>New features</value></term>
      <term><name>Release type</name><value>Bug fixes</value></term>
    </terms>
  </release>
  <release>
    <name>Semver Test 8.x-7.0-alpha1</name>
    <version>8.x-7.0-alpha1</version>
    <tag>SEMVER_TEST-8-x-7-0-alpha1</tag>
    <status>published</status>
    <release_link>http://example.com/semver_test-8-x-7-0-alpha1-release</release_link>
    <download_link>http://example.com/semver_test-8-x-7-0-alpha1.tar.gz</download_link>
    <date>1250424521</date>
    <terms>
      <term><name>Release type</name><value>New features</value></term>
      <term><name>Release type</name><value>Bug fixes</value></term>
    </terms>
</release>
</releases>
RELEASES;
  $doc = new DOMDocument();
  $doc->loadXML($contents);
  $element = $doc->getElementsByTagName('releases')->item(0);
  $releases_new = new DOMDocument();
  $releases_new->loadXML($releases);
  foreach ($releases_new->getElementsByTagName('release') as $new_release) {
    $imported = $doc->importNode($new_release, TRUE);
    $element->appendChild($imported);
  }

  $contents =  $doc->saveXML();

  $xml = new SimpleXMLElement($contents);
  $xml->supported_branches = "8.x-7.,8.0.,8.1.";
  $contents = (string) $xml->asXML();
  // attempt to clean up indentation.
  $contents = str_replace("</release><release>", "</release>\n  <release>", $contents);
  $contents = str_replace("</release></releases>", "  </release>\n</releases>", $contents);
  return $contents;
}

/**
 * Make test XML that tests 8.x-8.x.
 *
 * @param $contents
 *
 * @return string
 */
function convertLegacyMajor8($contents) {
  $placeholder = 'EIGHT_DOT_X';
  $contents = str_replace('8.x', 'EIGHT_DOT_X', $contents);
  $contents = str_replace('8-x', 'EIGHT_DASH_X', $contents);
  $contents = str_replace('8.', '9.', $contents);
  $contents = str_replace('8-', '9-', $contents);
  $contents = str_replace('EIGHT_DOT_X', '8.x', $contents);
  $contents = str_replace('EIGHT_DASH_X', '8-x', $contents);
  $contents = str_replace('8.x-7', '8.x-8', $contents);
  $contents = str_replace('8-x-7', '8-x-8', $contents);
  return $contents;
}


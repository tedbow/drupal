<?php

namespace Drupal\Core\Extension;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Version\DrupalSemver;
use Drupal\Core\Serialization\Yaml;

/**
 * Parses dynamic .info.yml files that might change during the page request.
 */
class InfoParserDynamic implements InfoParserInterface {

  /**
   * {@inheritdoc}
   */
  public function parse($filename) {
    if (!file_exists($filename)) {
      $parsed_info = [];
    }
    else {
      try {
        $parsed_info = Yaml::decode(file_get_contents($filename));
      }
      catch (InvalidDataTypeException $e) {
        throw new InfoParserException("Unable to parse $filename " . $e->getMessage());
      }
      $missing_keys = array_diff($this->getRequiredKeys(), array_keys($parsed_info));
      if (!empty($missing_keys)) {
        throw new InfoParserException('Missing required keys (' . implode(', ', $missing_keys) . ') in ' . $filename);
      }
      if (!isset($parsed_info['core']) && !isset($parsed_info['core_dependency'])) {
        throw new InfoParserException("The 'core' or the 'core_dependency' key must be present in " . $filename);
      }
      if (isset($parsed_info['core']) && !preg_match("/^\d\.x$/", $parsed_info['core'])) {
        throw new InfoParserException("The {$parsed_info['core']} is not valid value for  'core' in " . $filename);
      }
      if (isset($parsed_info['core_dependency'])) {
        if (DrupalSemver::satisfies('8.7.6', $parsed_info['core_dependency']) && !DrupalSemver::satisfies('8.0.0', $parsed_info['core_dependency'])) {
          throw new InfoParserException("The 'core_dependency' can not be used to specify compatibility specific version before 8.7.7 in " . $filename);
        }
      }
      else {
        $parsed_info['core_dependency'] = $parsed_info['core'];
      }
      if (isset($parsed_info['version']) && $parsed_info['version'] === 'VERSION') {
        $parsed_info['version'] = \Drupal::VERSION;
      }
      // Special backwards compatible handling profiles and their 'dependencies'
      // key.
      if ($parsed_info['type'] === 'profile' && isset($parsed_info['dependencies']) && !array_key_exists('install', $parsed_info)) {
        // Only trigger the deprecation message if we are actually using the
        // profile with the missing 'install' key. This avoids triggering the
        // deprecation when scanning all the available install profiles.
        global $install_state;
        if (isset($install_state['parameters']['profile'])) {
          $pattern = '@' . preg_quote(DIRECTORY_SEPARATOR . $install_state['parameters']['profile'] . '.info.yml') . '$@';
          if (preg_match($pattern, $filename)) {
            @trigger_error("The install profile $filename only implements a 'dependencies' key. As of Drupal 8.6.0 profile's support a new 'install' key for modules that should be installed but not depended on. See https://www.drupal.org/node/2952947.", E_USER_DEPRECATED);
          }
        }
        // Move dependencies to install so that if a profile has both
        // dependencies and install then dependencies are real.
        $parsed_info['install'] = $parsed_info['dependencies'];
        $parsed_info['dependencies'] = [];
      }
    }
    return $parsed_info;
  }

  /**
   * Returns an array of keys required to exist in .info.yml file.
   *
   * @return array
   *   An array of required keys.
   */
  protected function getRequiredKeys() {
    return ['type', 'name'];
  }

}

<?php

namespace Drupal\Core\Extension;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Version\DrupalSemver;
use Drupal\Core\Serialization\Yaml;

/**
 * Parses dynamic .info.yml files that might change during the page request.
 */
class InfoParserDynamic implements InfoParserInterface {

  const FIRST_CORE_DEPENDENCY_SUPPORTED_VERSION = '8.7.7';

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
        throw new InfoParserException("Invalid 'core' value \"{$parsed_info['core']}\" in " . $filename);
      }
      if (isset($parsed_info['core_dependency'])) {
        $supports_pre_core_dependency_version = $this->isConstraintSatisfiedByPreCoreDependencyCoreVersion($parsed_info['core_dependency']);
        // If the 'core_dependency' constraint does not satisfy any Drupal 8
        // versions before 8.7.7 then 'core' cannot be set or it will
        // effectively support all versions of Drupal 8 because
        // 'core_dependency' will be ignored in previous versions.
        if (!$supports_pre_core_dependency_version && isset($parsed_info['core'])) {
          throw new InfoParserException("The 'core_dependency' constraint ({$parsed_info['core_dependency']}) requires the 'core' not be set in " . $filename);
        }
        // 'core_dependency' can not be used to specify Drupal 8 versions before
        // 8.7.7 because these versions do not use the 'core_dependency' key.
        // Do not throw the exception if the constraint also is satisfied by
        // 8.0.0-alpha1 to allow constraints such as '^8' or '^8 || ^9'.
        if ($supports_pre_core_dependency_version && !DrupalSemver::satisfies('8.0.0-alpha1', $parsed_info['core_dependency'])) {
          throw new InfoParserException("The 'core_dependency' can not be used to specify compatibility specific version before " . static::FIRST_CORE_DEPENDENCY_SUPPORTED_VERSION . " in $filename");
        }
      }
      else {
        $parsed_info['core_dependency'] = $parsed_info['core'];
      }

      // Determine if the extension is compatible with the current version of
      // Drupal core.
      $parsed_info['core_incompatible'] = !DrupalSemver::satisfies(\Drupal::VERSION, $parsed_info['core_dependency']);

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

  /**
   * Determines if a constraint is satisfied by core without 'core_dependency'.
   *
   * @param string $constraint
   *   A core semantic version constraint.
   *
   * @return bool
   *   TRUE if the constraint is satisfied by a core version that does not
   *   support the 'core_dependency' key in info.yml files.
   */
  protected function isConstraintSatisfiedByPreCoreDependencyCoreVersion($constraint) {
    static $evaluated_constraints = [];
    if (!isset($evaluated_constraints[$constraint])) {
      foreach (range(0, 7) as $minor) {
        foreach (range(0, 20) as $patch) {
          $minor_version = "8.$minor.$patch";
          if ($minor_version === static::FIRST_CORE_DEPENDENCY_SUPPORTED_VERSION) {
            $evaluated_constraints[$constraint] = FALSE;
            return $evaluated_constraints[$constraint];
          }
          if (DrupalSemver::satisfies($minor_version, $constraint)) {
            $evaluated_constraints[$constraint] = TRUE;
            return $evaluated_constraints[$constraint];
          }
          if ($patch === 0) {
            foreach (['alpha1', 'beta1', 'rc1'] as $suffix) {
              $pre_release_version = "$minor_version-$suffix";
              if (DrupalSemver::satisfies($minor_version, $pre_release_version)) {
                $evaluated_constraints[$constraint] = TRUE;
                return $evaluated_constraints[$constraint];
              }
            }
          }
        }
      }
    }
    $evaluated_constraints[$constraint] = FALSE;
    return $evaluated_constraints[$constraint];
  }

}

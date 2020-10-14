<?php

namespace Drupal\update;

use Drupal\Core\Extension\ExtensionList;

/**
 * Provides a helper methods to get project info.
 */
trait ProjectInfoTrait {

  /**
   * The extension lists.
   *
   * @var \Drupal\Core\Extension\ExtensionList[]
   */
  protected $extensionLists;

  /**
   * Get extension list.
   *
   * @param string $extension_type
   *   The extension type.
   *
   * @return \Drupal\Core\Extension\ExtensionList
   *   The extension list service.
   */
  protected function getExtensionList(string $extension_type) {
    if (isset($this->extensionLists[$extension_type])) {
      return $this->extensionLists[$extension_type];
    }
    throw new \UnexpectedValueException("Invalid extension type: $extension_type");
  }

  /**
   * Sets the extension lists.
   *
   * @param \Drupal\Core\Extension\ExtensionList $module_list
   *   The module extension list.
   * @param \Drupal\Core\Extension\ExtensionList $theme_list
   *   The theme extension list.
   * @param \Drupal\Core\Extension\ExtensionList $profile_list
   *   The profile extension list.
   */
  protected function setExtensionLists(ExtensionList $module_list, ExtensionList $theme_list, ExtensionList $profile_list) {
    $this->extensionLists = [
      'module' => $module_list,
      'theme' => $theme_list,
      'profile' => $profile_list,
    ];
  }

  /**
   * Returns an array of info files information of available extensions.
   *
   * @param string $extension_type
   *   The extension type.
   *
   * @return array
   *   An associative array of extension information arrays, keyed by extension
   *   name.
   */
  protected function getInfos(string $extension_type) {
    $file_paths = $this->getExtensionList($extension_type)->getPathnames();
    $infos = $this->getExtensionList($extension_type)->getAllAvailableInfo();
    array_walk($infos, function (array &$info, $key) use ($file_paths) {
      $info['packaged'] = isset($info['datestamp']) ? $info['datestamp'] : FALSE;
      $info['install path'] = $file_paths[$key] ? dirname($file_paths[$key]) : '';
      $info['project'] = $this->getProjectName($key, $info);
      $info['version'] = $this->getExtensionVersion($info);
    });
    $system = isset($infos['system']) ? $infos['system'] : NULL;
    $infos = array_filter($infos, static function (array $info, $project_name) {
      return $info && $info['project'] === $project_name;
    }, ARRAY_FILTER_USE_BOTH);
    if ($system) {
      $infos['drupal'] = $system;
      // From 8.8.0 onward, always use packaged for core because non-packaged
      // will no longer make any sense.
      if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
        $infos['drupal']['packaged'] = TRUE;
      }

    }
    return $infos;
  }

  /**
   * Get the extension version.
   *
   * @param array $info
   *   The extension's info.
   *
   * @return string|null
   *   The version or NULL if undefined.
   */
  protected function getExtensionVersion(array $info) {
    $extension_name = $info['project'];
    if (isset($info['version']) && strpos($info['version'], '-dev') === FALSE) {
      return $info['version'];
    }
    // Handle experimental modules from core.
    if (strpos($info['install path'], 'core') === 0) {
      return $this->getExtensionList('module')->get('system')->info['version'];
    }
    \Drupal::logger('updates')->error('Version cannot be located for @extension', ['@extension' => $extension_name]);
    return NULL;
  }

  /**
   * Get the extension's project name.
   *
   * @param string $extension_name
   *   The extension name.
   * @param array $info
   *   The extension's info.
   *
   * @return string
   *   The project name or fallback to extension name if project is undefined.
   */
  protected function getProjectName(string $extension_name, array $info) {
    $project_name = $extension_name;
    if (isset($info['project'])) {
      $project_name = $info['project'];
    }
    elseif ($composer_json = $this->getComposerJson($extension_name, $info)) {
      if (isset($composer_json['name'])) {
        $project_name = $this->getSuffix($composer_json['name'], '/', $extension_name);
      }
    }
    if (strpos($info['install path'], 'core') === 0) {
      $project_name = 'drupal';
    }
    return $project_name;
  }

  /**
   * Get string suffix.
   *
   * @param string $string
   *   The string to parse.
   * @param string $needle
   *   The needle.
   * @param string $default
   *   The default value.
   *
   * @return string
   *   The sub string.
   */
  protected function getSuffix(string $string, string $needle, string $default) {
    $pos = strrpos($string, $needle);
    return $pos === FALSE ? $default : substr($string, ++$pos);
  }

  /**
   * Get the composer.json as a JSON array.
   *
   * @param string $extension_name
   *   The extension name.
   * @param array $info
   *   The extension's info.
   *
   * @return array|null
   *   The composer.json as an array or NULL.
   */
  protected function getComposerJson(string $extension_name, array $info) {
    try {
      if ($directory = drupal_get_path($info['type'], $extension_name)) {
        $composer_json = $directory . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($composer_json)) {
          return json_decode(file_get_contents($composer_json), TRUE);
        }
      }
    }
    catch (\Throwable $exception) {
      \Drupal::logger('updates')->error('Composer.json could not be located for @extension', ['@extension' => $extension_name]);
    }
    return NULL;
  }

}

<?php

namespace Drupal\auto_updates;

use Composer\Semver\Semver;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\system\ExtensionVersion;
use Drupal\update\UpdateFetcherInterface;

/**
 * Defines a service that calculates available updates for core.
 */
class UpdateCalculator {

  use MessengerTrait;

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * UpdateCalculator constructor.
   */
  public function __construct(ModuleHandler $module_handler) {
    $this->moduleHandler = $module_handler;
  }


  /**
   * Gets the support update version for core, if any.
   */
  public function getSupportedUpdateRelease() {
    $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');
    $available = update_get_available(TRUE);
    // Only calculate available updates for Drupal core.
    $available = ['drupal' => $available['drupal']];
    $project_data = update_calculate_project_data($available);
    if (empty($project_data['drupal']) || $project_data['drupal']['status'] === UpdateFetcherInterface::NOT_FETCHED || empty($project_data['drupal']['existing_version'])) {
      return NULl;
    }
    $project = $project_data['drupal'];
    $existing_version = $project['existing_version'];
    if (!empty($project['recommended'])) {
      $recommended_release = $project['releases'][$project['recommended']];
      if ($this->isSupported($recommended_release, $existing_version)) {
        return $recommended_release;
      }
    }
    if (!empty($project['security updates'])) {
      foreach ($project['security updates'] as $security_update) {
        // Return the first security update that matches.
        if ($this->isSupported($security_update, $existing_version)) {
          return $security_update;
        }
      }
    }

    return NULL;

  }

  private function isSupported(array $recommended_release, string $existing_version_string) {
    $recommended_release_version_string = $recommended_release['version'];
    $recommended_version = ExtensionVersion::createFromVersionString($recommended_release_version_string);
    $existing_version = ExtensionVersion::createFromVersionString($existing_version_string);
    if ($recommended_version->getMajorVersion() !== $existing_version->getMajorVersion()
      || $recommended_version->getMinorVersion() !== $existing_version->getMinorVersion()
        // Never downgrade.
      || Semver::satisfies($existing_version_string, '>' . $recommended_release_version_string)
      ) {
      return NULL;
    }
    return TRUE;
  }


}

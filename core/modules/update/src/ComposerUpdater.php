<?php


namespace Drupal\update;


use Composer\Console\Application;
use Effulgentsia\StagedComposerUpdate\StagedUpdateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class ComposerUpdater {

  public static function processBatch(array $project_versions, array &$context) {
    $child_app = new Application();
    $child_app->add(new StagedUpdateCommand());
    $child_app->setAutoExit(FALSE);
    $packages = static::createPackageStrings($project_versions);
    $child_input = new ArrayInput(
      [
        'command' => 'staged-update',
        'packages' => $packages,
      ]
    );
    // Composer\Factory::getHomeDir() method
    // needs COMPOSER_HOME environment variable set
    $class_loader_reflection = new \ReflectionObject(\Drupal::service('class_loader'));
    $vendor_dir = dirname($class_loader_reflection->getFileName(), 2);
    $home =  "$vendor_dir/bin/composer";
    putenv('COMPOSER_HOME=' . $home);
    $result = $child_app->run($child_input, new NullOutput());
    $context['finished'] = 1;
    return;


  }

  private static function createPackageStrings(array $project_versions) {
    $packages = [];
    foreach ($project_versions as $project => $project_version) {
      if ($project !== 'drupal') {
        $packages[$project] = "drupal/$project:" . static::convertToComposerVersion($project_version);
      }
    }
    return $packages;
  }

  private static function convertToComposerVersion($project_version) {
    return str_replace('8.x-', '', $project_version) . '.0';
  }
}

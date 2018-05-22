<?php

namespace Drupal\TestSite\Commands;

use Drupal\Core\Test\FunctionalTestSetupTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TestSiteInstallModulesCommand
 *
 * @internal
 */
class TestSiteInstallModulesCommand extends Command {

  use FunctionalTestSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('modules-install')
      ->setDescription('Installs modules on a test Drupal site')
      ->setHelp('Help.')
      ->addOption('modules', NULL, InputOption::VALUE_REQUIRED, 'A list of modules you want to enable.')
      ->addOption('base-url', NULL, InputOption::VALUE_OPTIONAL, 'Base URL for site under test. Defaults to the environment variable SIMPLETEST_BASE_URL.', getenv('SIMPLETEST_BASE_URL'))
      ->addOption('db-url', NULL, InputOption::VALUE_OPTIONAL, 'URL for database. Defaults to the environment variable SIMPLETEST_DB.', getenv('SIMPLETEST_DB'))
      ->addUsage('--modules layout_builder settings_tray');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $db_url = $input->getOption('db-url');
    $base_url = $input->getOption('base-url');
    putenv("SIMPLETEST_DB=$db_url");
    putenv("SIMPLETEST_BASE_URL=$base_url");


    $this->initConfig($this->initKernel(\Drupal::request()));
    \Drupal::service('module_installer')->install($input->getOption('modules'));
    return;
  }
}

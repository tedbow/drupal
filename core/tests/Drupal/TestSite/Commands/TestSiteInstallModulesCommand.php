<?php

namespace Drupal\TestSite\Commands;

use Drupal\Core\Test\FunctionalTestSetupTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestSiteInstallModulesCommand extends Command {

  use FunctionalTestSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('module-install')
      ->setDescription('Installs modules on a test Drupal site')
      ->setHelp('Helpy.')
      ->addOption('modules', NULL, InputOption::VALUE_REQUIRED, 'The path to a PHP file containing a class to setup configuration used by the test, for example, core/tests/Drupal/TestSite/TestSiteInstallTestScript.php.')
      ->addUsage('--modules layout_builder settings_tray');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $container = $this->initKernel(\Drupal::request());
    $modules = explode(' ', $input->getOption('modules'));
    var_dump($modules);
    $container->get('module_installer')->install($modules);
  }


}

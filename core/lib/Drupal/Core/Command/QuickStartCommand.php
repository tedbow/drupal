<?php

namespace Drupal\Core\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs a Drupal site and starts a webserver for local testing/development.
 *
 * Wraps 'install' and 'server' commands.
 *
 * @see \Drupal\Core\Command\InstallCommand
 * @see \Drupal\Core\Command\ServerCommand
 */
class QuickStartCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('quick-start')
      ->setDescription('Installs a Drupal site and runs a web server. This is not meant for production or any custom development. It is a quick and easy way to get Drupal running.')
      ->addArgument('install-profile', InputArgument::OPTIONAL, 'Install profile to install the site in.')
      ->addOption('langcode', NULL, InputOption::VALUE_OPTIONAL, 'The language to install the site in. Defaults to en.', 'en')
      ->addOption('site-name', NULL, InputOption::VALUE_OPTIONAL, 'Set the site name. Defaults to Drupal.', 'Drupal')
      ->addOption('suppress-login', 's', InputOption::VALUE_NONE, 'Disable opening a login URL in a browser.')
      ->addOption('show-server-output', NULL, InputOption::VALUE_NONE, 'Show output from the PHP development server.')
      ->addUsage('demo_umami --langcode fr');

    parent::configure();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $command = $this->getApplication()->find('install');

    $arguments = [
      'command' => 'install',
      'install-profile' => $input->getArgument('install-profile'),
      '--langcode' => $input->getOption('langcode'),
      '--site-name' => $input->getOption('site-name'),
    ];

    $installInput = new ArrayInput($arguments);
    $returnCode = $command->run($installInput, $output);

    if ($returnCode === 0) {
      $command = $this->getApplication()->find('server');
      $arguments = [
        'command' => 'server',
      ];
      if ($input->getOption('suppress-login')) {
        $arguments['--suppress-login'] = TRUE;
      }
      if ($input->getOption('show-server-output')) {
        $arguments['--show-server-output'] = TRUE;
      }
      $serverInput = new ArrayInput($arguments);
      $returnCode = $command->run($serverInput, $output);
    }
    return $returnCode;
  }

}

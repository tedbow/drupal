<?php

namespace Drupal\Core\Command;

use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\InfoParserDynamic;
use Drupal\Core\Site\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installs a Drupal site for local testing/development.
 */
class InstallCommand extends Command {

  /**
   * The class loader.
   *
   * @var object
   */
  protected $classLoader;

  /**
   * Constructs a new InstallCommand command.
   *
   * @param object $class_loader
   *   The class loader.
   */
  public function __construct($class_loader) {
    parent::__construct('install');
    $this->classLoader = $class_loader;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('install')
      ->setDescription('Installs a Drupal dev site. This is not meant for production or any custom development. It is a quick and easy way to get Drupal running.')
      ->addArgument('install-profile', InputArgument::OPTIONAL, 'Install profile to install the site in.')
      ->addOption('langcode', NULL, InputOption::VALUE_OPTIONAL, 'The language to install the site in. Defaults to en.', 'en')
      ->addOption('site-name', NULL, InputOption::VALUE_OPTIONAL, 'Set the site name. Defaults to Drupal.', 'Drupal')
      ->addUsage('demo_umami --langcode fr');

    parent::configure();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);
    if (!extension_loaded('pdo_sqlite')) {
      $io->getErrorStyle()->error('You must have the SQLite PHP extension installed. See https://secure.php.net/manual/en/sqlite.installation.php for instructions.');
      return 1;
    }

    // Change the directory to the Drupal root.
    chdir(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

    // Check whether there is already an installation.
    if ($this->isDrupalInstalled()) {
      // Do not fail if the site is already installed so this command can be
      // chained with ServerCommand.
      $output->writeln('Drupal is already installed.');
      return;
    }

    return $this->install($this->classLoader, $io, $this->selectProfile($input, $io), $input->getOption('langcode'), $this->getSitePath(), $input->getOption('site-name'));
  }

  /**
   * Returns whether there is already an existing Drupal installation.
   *
   * @return bool
   */
  protected function isDrupalInstalled() {
    try {
      $kernel = new DrupalKernel('prod', $this->classLoader, FALSE);
      $kernel::bootEnvironment();
      $kernel->setSitePath($this->getSitePath());
      Settings::initialize($kernel->getAppRoot(), $kernel->getSitePath(), $this->classLoader);
      $kernel->boot();
    }
    catch (ConnectionNotDefinedException $e) {
      return FALSE;
    }
    return !empty(Database::getConnectionInfo());
  }

  /**
   * Installs Drupal with specified installation profile.
   *
   * @param object $class_loader
   *   The class loader.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The Symfony output decorator.
   * @param string $profile
   *   The installation profile to use.
   * @param string $langcode
   *   The language to install the site in.
   * @param string $site_path
   *   The path to install the site to, like 'sites/default'.
   * @param string $site_name
   *   The site name.
   *
   * @throws \Exception
   *   Thrown when failing to create the $site_path directory or settings.php.
   */
  protected function install($class_loader, SymfonyStyle $io, $profile, $langcode, $site_path, $site_name) {
    $parameters = [
      'interactive' => FALSE,
      'site_path' => $site_path,
      'parameters' => [
        'profile' => $profile,
        'langcode' => $langcode,
      ],
      'forms' => [
        'install_settings_form' => [
          'driver' => 'sqlite',
          'sqlite' => [
            'database' => $site_path . '/files/.sqlite',
          ],
        ],
        'install_configure_form' => [
          'site_name' => $site_name,
          'site_mail' => 'drupal@localhost',
          'account' => [
            'name' => 'admin',
            'mail' => 'admin@localhost',
            'pass' => [
              'pass1' => 'test',
              'pass2' => 'test',
            ],
          ],
          'enable_update_status_module' => TRUE,
          // form_type_checkboxes_value() requires NULL instead of FALSE values
          // for programmatic form submissions to disable a checkbox.
          'enable_update_status_emails' => NULL,
        ],
      ],
    ];

    // Create the directory and settings.php if not there so that the installer
    // works.
    if (!is_dir($site_path)) {
      if (!mkdir($site_path, 0775)) {
        throw new \RuntimeException("Failed to create directory $site_path");
      }
    }
    if (!file_exists("{$site_path}/settings.php")) {
      if (!copy('sites/default/default.settings.php', "{$site_path}/settings.php")) {
        throw new \RuntimeException("Copying sites/default/default.settings.php to {$site_path}/settings.php failed.");
      }
    }

    $io->writeln('Drupal installation started. This could take a minute.');
    require_once 'core/includes/install.core.inc';

    install_drupal($class_loader, $parameters, function ($install_state) use ($io) {
      static $started = FALSE;
      if (!$started) {
        $started = TRUE;
        $tasks = install_tasks_to_perform($install_state);
        // We've already done 1.
        $io->progressStart(count($tasks) + 1);
      }
      $io->progressAdvance();
    });
    $io->progressFinish();
    $success_message = t('Congratulations, you installed @drupal!', [
      '@drupal' => drupal_install_profile_distribution_name(),
    ], ['langcode' => $langcode]);
    $io->writeln((string) $success_message);
  }

  /**
   * Gets the site path.
   *
   * Defaults to 'sites/default'. For testing purposes this can be overridden
   * using the DRUPAL_DEV_SITE_PATH environment variable.
   *
   * @return string
   *   The site path to use.
   */
  protected function getSitePath() {
    return getenv('DRUPAL_DEV_SITE_PATH') ?: 'sites/default';
  }

  /**
   * Selects the install profile to use.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Console input.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   Symfony style output decorator.
   *
   * @return string
   *   The selected install profile.
   *
   * @see _install_select_profile()
   * @see \Drupal\Core\Installer\Form\SelectProfileForm
   */
  protected function selectProfile(InputInterface $input, SymfonyStyle $io) {
    // If the user has provided one on the command use that.
    if ($selected_profile = $input->getArgument('install-profile')) {
      return $selected_profile;
    }

    // Build a list of all available profiles.
    $listing = new ExtensionDiscovery(getcwd(), FALSE);
    $listing->setProfileDirectories([]);
    $names = [];
    $info_parser = new InfoParserDynamic();
    foreach ($listing->scan('profile') as $profile) {
      $details = $info_parser->parse($profile->getPathname());
      // Don't show hidden profiles.
      if (!empty($details['hidden'])) {
        continue;
      }
      // Distributions are automatically selected.
      if (!empty($details['distribution'])) {
        return $profile->getName();
      }
      // Determine the name of the profile; default to file name if defined name
      // is unspecified.
      $name = isset($details['name']) ? $details['name'] : $profile->getName();
      $description = isset($details['description']) ? $details['description'] : $name;
      $names[$profile->getName()] = $description;
    }

    // Display alphabetically by human-readable name, but always put the core
    // profiles first (if they are present in the filesystem).
    natcasesort($names);
    if (isset($names['minimal'])) {
      // If the expert ("Minimal") core profile is present, put it in front of
      // any non-core profiles rather than including it with them
      // alphabetically, since the other profiles might be intended to group
      // together in a particular way.
      $names = ['minimal' => $names['minimal']] + $names;
    }
    if (isset($names['standard'])) {
      // If the default ("Standard") core profile is present, put it at the very
      // top of the list. This profile will have its radio button pre-selected,
      // so we want it to always appear at the top.
      $names = ['standard' => $names['standard']] + $names;
    }
    reset($names);
    return $io->choice('Select an installation profile', $names, current($names));
  }

}

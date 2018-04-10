<?php

namespace Drupal\Core\Command;

use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\DrupalKernel;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the PHP webserver for a Drupal site for local testing/development.
 */
class ServerCommand extends Command {

  /**
   * The class loader.
   *
   * @var object
   */
  protected $classLoader;

  /**
   * Constructs a new ServerCommand command.
   *
   * @param object $class_loader
   *   The class loader.
   */
  public function __construct($class_loader) {
    parent::__construct('server');
    $this->classLoader = $class_loader;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setDescription('Starts up a webserver for a site.')
      ->addOption('suppress-login', 's', InputOption::VALUE_NONE, 'Disable opening a login URL in a browser.')
      ->addOption('show-server-output', NULL, InputOption::VALUE_NONE, 'Show output from the PHP development server.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);
    try {
      $kernel = $this->boot();
    }
    catch (ConnectionNotDefinedException $e) {
      $io->getErrorStyle()->error('No installation found. Use the \'install\' command.');
      return 1;
    }
    $this->start($kernel, $input, $io);
  }

  /**
   * Boots up a Drupal environment.
   *
   * @return \Drupal\Core\DrupalKernelInterface
   *   The Drupal kernel.
   *
   * @throws \Exception
   *   Exception thrown if kernel does not boot.
   */
  protected function boot() {
    $kernel = new DrupalKernel('prod', $this->classLoader, FALSE);
    $kernel::bootEnvironment();
    $kernel->setSitePath($this->getSitePath());
    Settings::initialize($kernel->getAppRoot(), $kernel->getSitePath(), $this->classLoader);
    $kernel->boot();
    // Some services require a request to work. For example, CommentManager.
    // This is needed as generating the URL fires up entity load hooks.
    $kernel->getContainer()
      ->get('request_stack')
      ->push(Request::createFromGlobals());

    return $kernel;
  }

  /**
   * Finds an available port.
   *
   * @return int
   *   The available port.
   *
   * @throws \Exception
   *   Exception thrown when listening to a socket failed.
   */
  protected function getAvailablePort() {
    $address = '0.0.0.0';
    $port = 0;
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($socket, $address, $port);
    if (!socket_listen($socket, 5)) {
      $message = socket_strerror(socket_last_error());
      throw new \Exception($message);
    }
    socket_getsockname($socket, $address, $port);
    return $port;
  }

  /**
   * Opens a URL in your system default browser.
   *
   * @param string $url
   *   The URL to browser to.
   *
   * @return int
   *   The exit code of opening up a browser.
   */
  protected function openBrowser($url, SymfonyStyle $io) {
    $url = escapeshellarg($url);

    $is_windows = defined('PHP_WINDOWS_VERSION_BUILD');
    if ($is_windows) {
      $process = new Process('start "web" explorer "' . $url . '"');
      return $process->run();
    }

    $is_linux = (new Process('which xdg-open'))->run();
    $is_osx = (new Process('which open'))->run();
    if ($is_linux === 0) {
      return (new Process('xdg-open ' . $url))->run();
    }
    elseif ($is_osx === 0) {
      return (new Process('open ' . $url))->run();
    }
    $io->getErrorStyle()
      ->error('No suitable browser opening command found, open yourself: ' . $url);
  }

  /**
   * Gets a one time login URL for user 1.
   *
   * @return string
   *   The one time login URL for user 1.
   */
  protected function getOneTimeLoginUrl() {
    $user = User::load(1);
    \Drupal::moduleHandler()->load('user');
    return user_pass_reset_url($user);
  }

  /**
   * Starts up a webserver with a running Drupal.
   *
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   *   The Drupal kernel.
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The IO.
   */
  protected function start(DrupalKernelInterface $kernel, InputInterface $input, SymfonyStyle $io) {
    $finder = new PhpExecutableFinder();
    $binary = $finder->find();
    if ($binary === FALSE) {
      throw new \RuntimeException('Unable to find the PHP binary.');
    }

    $port = $this->getAvailablePort();
    $process = new Process([
      $binary,
      '-S',
      'localhost:' . $port,
      '.ht.router.php',
    ], $kernel->getAppRoot(), [], NULL, NULL);

    // If TTY is available and the --show-server-output is used, run the command
    // using it so that the full colored output from PHP's in-built webserver is
    // displayed.
    if (DIRECTORY_SEPARATOR !== '\\' && $input->getOption('show-server-output')) {
      $process->setTty(TRUE);
      $process->start();
    }
    else {
      $io->writeln('Starting webserver on http://localhost:' . $port);
      $io->writeln('Press Ctrl-C to quit.');
      $output = NULL;
      if ($input->getOption('show-server-output')) {
        $output = function ($type, $buffer) {
          echo $buffer;
        };
      }
      $process->start($output);
    }

    $one_time_login = "http://localhost:$port{$this->getOneTimeLoginUrl()}/login";
    if ($input->getOption('suppress-login')) {
      $io->writeln('One time login url: ' . $one_time_login);
    }
    else {
      // Should we redirect to the front page?
      if ($this->openBrowser("$one_time_login?destination=" . urlencode("/"), $io) === 1) {
        $io->error('Error while opening up a one time login URL');
      }
    }
    // Hang until the process is killed.
    $process->wait();
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

}

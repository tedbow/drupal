<?php

namespace Drupal\Tests\Core\Command;

use Drupal\Core\Test\TestDatabase;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Tests the quick-start commands.
 *
 * These tests are run in a separate process because they load Drupal code via
 * an include.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group Command
 */
class QuickStartTest extends TestCase {

  /**
   * The PHP executable path.
   *
   * @var string
   */
  protected $php;

  /**
   * A test database object.
   *
   * @var \Drupal\Core\Test\TestDatabase
   */
  protected $testDb;

  /**
   * The Drupal root directory.
   *
   * @var string
   */
  protected $root;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    if (!is_writable('sites/simpletest')) {
      $this->markTestSkipped('This test requires a writable sites/simpletest directory');
    }
    $php_executable_finder = new PhpExecutableFinder();
    $this->php = $php_executable_finder->find();
    $this->root = dirname(dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__))));
    chdir($this->root);
    // Get a lock and a valid site path.
    $this->testDb = new TestDatabase();
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    if ($this->testDb) {
      $test_site_directory = $this->root . DIRECTORY_SEPARATOR . $this->testDb->getTestSitePath();
      if (file_exists($test_site_directory)) {
        // @todo use the tear down command from
        //   https://www.drupal.org/project/drupal/issues/2926633
        // Delete test site directory.
        $this->fileUnmanagedDeleteRecursive($test_site_directory, [
          BrowserTestBase::class,
          'filePreDeleteCallback'
        ]);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests the quick-start command.
   */
  public function testQuickStartCommand() {
    // Install a site using the standard profile to ensure the one time login
    // link generation works.
    $install_command = "{$this->php} core/scripts/drupal quick-start standard --site-name='Test site {$this->testDb->getDatabasePrefix()}' --suppress-login";
    $process = new Process($install_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->testDb->getTestSitePath()]);
    $process->inheritEnvironmentVariables();
    $process->setTimeout(500);
    $process->start();
    $guzzle = new Client();
    $port = FALSE;
    while ($process->isRunning()) {
      if (preg_match('/localhost:(\d+)/', $process->getOutput(), $match)) {
        $port = $match[1];
        break;
      }
      // Wait for more output.
      sleep(1);
    }
    $this->assertContains('Drupal installation started. This could take a minute.', $process->getOutput());
    $this->assertContains('Drupal successfully installed.', $process->getOutput());
    $this->assertNotFalse($port, "Web server running on port $port");
    $this->assertContains("localhost:$port/user/reset/1/", $process->getOutput());

    // Give the server a couple of seconds to be ready.
    sleep(2);

    // Generate a cookie so we can make a request against the installed site.
    include $this->root . '/core/includes/bootstrap.inc';
    define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
    chmod($this->testDb->getTestSitePath(), 0755);
    $cookieJar = CookieJar::fromArray([
      'SIMPLETEST_USER_AGENT' => drupal_generate_test_ua($this->testDb->getDatabasePrefix())
    ], 'localhost');

    $response = $guzzle->get('http://localhost:' . $port, ['cookies' => $cookieJar]);
    $content = (string) $response->getBody();
    $this->assertContains('Test site ' . $this->testDb->getDatabasePrefix(), $content);

    // Stop the web server.
    $process->stop();
  }

  /**
   * Tests the quick-start commands.
   */
  public function testQuickStartInstallAndServerCommands() {
    // Install a site.
    $install_command = "{$this->php} core/scripts/drupal install testing --site-name='Test site {$this->testDb->getDatabasePrefix()}'";
    $install_process = new Process($install_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->testDb->getTestSitePath()]);
    $install_process->inheritEnvironmentVariables();
    $install_process->setTimeout(500);
    $result = $install_process->run();
    $this->assertContains('Drupal installation started. This could take a minute.', $install_process->getOutput());
    $this->assertContains('Drupal successfully installed.', $install_process->getOutput());
    $this->assertSame(0, $result);

    // Run the PHP built-in webserver.
    $server_command = "{$this->php} core/scripts/drupal server --suppress-login";
    $server_process = new Process($server_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->testDb->getTestSitePath()]);
    $server_process->inheritEnvironmentVariables();
    $server_process->start();
    $guzzle = new Client();
    $port = FALSE;
    while ($server_process->isRunning()) {
      if (preg_match('/localhost:(\d+)/', $server_process->getOutput(), $match)) {
        $port = $match[1];
        break;
      }
      // Wait for more output.
      sleep(1);
    }
    $this->assertEquals('', $server_process->getErrorOutput());
    $this->assertContains("localhost:$port/user/reset/1/", $server_process->getOutput());
    $this->assertNotFalse($port, "Web server running on port $port");

    // Give the server a couple of seconds to be ready.
    sleep(2);

    // Generate a cookie so we can make a request against the installed site.
    include $this->root . '/core/includes/bootstrap.inc';
    define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
    chmod($this->testDb->getTestSitePath(), 0755);
    $cookieJar = CookieJar::fromArray([
      'SIMPLETEST_USER_AGENT' => drupal_generate_test_ua($this->testDb->getDatabasePrefix())
    ], 'localhost');

    $response = $guzzle->get('http://localhost:' . $port, ['cookies' => $cookieJar]);
    $content = (string) $response->getBody();
    $this->assertContains('Test site ' . $this->testDb->getDatabasePrefix(), $content);

    // Try to re-install over the top of an existing site.
    $install_command = "{$this->php} core/scripts/drupal install testing --site-name='Test another site {$this->testDb->getDatabasePrefix()}'";
    $install_process = new Process($install_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->testDb->getTestSitePath()]);
    $install_process->inheritEnvironmentVariables();
    $install_process->setTimeout(500);
    $result = $install_process->run();
    $this->assertContains('Drupal is already installed.', $install_process->getOutput());
    $this->assertSame(0, $result);

    // Ensure the site name has not changed.
    $response = $guzzle->get('http://localhost:' . $port, ['cookies' => $cookieJar]);
    $content = (string) $response->getBody();
    $this->assertContains('Test site ' . $this->testDb->getDatabasePrefix(), $content);

    // Stop the web server.
    $server_process->stop();
  }

  /**
   * Tests the server command when there is no installation.
   */
  public function testServerWithNoInstall() {
    $server_command = "{$this->php} core/scripts/drupal server --suppress-login";
    $server_process = new Process($server_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->testDb->getTestSitePath()]);
    $server_process->inheritEnvironmentVariables();
    $server_process->run();
    $this->assertContains('No installation found. Use the \'install\' command.', $server_process->getErrorOutput());
  }

  /**
   * Deletes all files and directories in the specified path recursively.
   *
   * Note this method has no dependencies on Drupal core to ensure that the
   * test site can be torn down even if something in the test site is broken.
   *
   * @param string $path
   *   A string containing either an URI or a file or directory path.
   * @param callable $callback
   *   (optional) Callback function to run on each file prior to deleting it and
   *   on each directory prior to traversing it. For example, can be used to
   *   modify permissions.
   *
   * @return bool
   *   TRUE for success or if path does not exist, FALSE in the event of an
   *   error.
   *
   * @see file_unmanaged_delete_recursive()
   */
  protected function fileUnmanagedDeleteRecursive($path, $callback = NULL) {
    if (isset($callback)) {
      call_user_func($callback, $path);
    }
    if (is_dir($path)) {
      $dir = dir($path);
      while (($entry = $dir->read()) !== FALSE) {
        if ($entry == '.' || $entry == '..') {
          continue;
        }
        $entry_path = $path . '/' . $entry;
        $this->fileUnmanagedDeleteRecursive($entry_path, $callback);
      }
      $dir->close();

      return rmdir($path);
    }
    return unlink($path);
  }

}

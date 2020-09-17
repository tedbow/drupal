<?php

namespace Drupal\update\Psa;

use Composer\Semver\VersionParser;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\update\UpdateManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/**
 * Service class to get Public Service Messages.
 */
class UpdatesPsa implements UpdatesPsaInterface {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  protected const MALFORMED_JSON_EXCEPTION_CODE = 1000;

  /**
   * This module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The update manager.
   *
   * @var \Drupal\update\UpdateManagerInterface
   */
  protected $updateManager;

  /**
   * Constructs a new UpdatesPsa object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
   * @param \Drupal\update\UpdateManagerInterface $update_manager
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, TimeInterface $time, Client $client, UpdateManagerInterface $update_manager, LoggerInterface $logger) {
    $this->config = $config_factory->get('update.settings');
    $this->cache = $cache;
    $this->time = $time;
    $this->httpClient = $client;
    $this->updateManager = $update_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicServiceMessages() {
    $messages = [];

    if ($cache = $this->cache->get('updates_psa')) {
      $response = $cache->data;
    }
    else {
      $psa_endpoint = $this->config->get('psa.endpoint');
      try {
        $response = $this->httpClient->get($psa_endpoint)
          ->getBody()
          ->getContents();
        $this->cache->set('updates_psa', $response, $this->time->getCurrentTime() + $this->config->get('psa.check_frequency'));
      }
      catch (TransferException $exception) {
        $this->logger->error($exception->getMessage());
        throw $exception;
      }
    }

    $json_payload = json_decode($response, TRUE);
    if ($json_payload !== NULL) {
      foreach ($json_payload as $json) {
        try {
          $sa = SecurityAnnouncement::createFromArray($json);
        }
        catch (\UnexpectedValueException $unexpected_value_exception) {
          $this->logger->error('PSA malformed: ' . $unexpected_value_exception->getMessage());
          throw new \UnexpectedValueException($unexpected_value_exception->getMessage(), static::MALFORMED_JSON_EXCEPTION_CODE);
        }

        if ($sa->getProjectType() !== 'core' && !$this->isValidProject($sa->getProject())) {
          continue;
        }
        if ($sa->isPsa() || $this->matchesInstalledVersion($sa)) {
          $messages[] = $this->message($sa);
        }
      }
    }
    else {
      $this->logger->error('Drupal PSA JSON is malformed: @response', ['@response' => $response]);
      throw new \UnexpectedValueException('Drupal PSA JSON is malformed.', static::MALFORMED_JSON_EXCEPTION_CODE);
    }

    return $messages;
  }

  /**
   * Gets a message from an exception thrown by ::getPublicServiceMessages().
   *
   * @param \Exception $exception
   *   The exception throw by ::getPublicServiceMessages().
   * @param bool $throw_unexpected_exceptions
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The message to display.
   *
   * @throws \Exception Throw if the exception is not expected.
   */
  public static function getErrorMessageFromException(\Exception $exception, bool $throw_unexpected_exceptions = TRUE) {
    if  ($exception instanceof TransferException) {
      return t(
        'Unable to retrieve PSA information from :url.',
        [':url' => \Drupal::config('update.settings')->get('psa.endpoint')]
      );
    }
    elseif (get_class($exception) === \UnexpectedValueException::class && $exception->getCode() === static::MALFORMED_JSON_EXCEPTION_CODE) {
      return t('Drupal PSA JSON is malformed.');
    }
    if ($throw_unexpected_exceptions) {
      throw $exception;
    }
    return $exception->getMessage();
  }

  /**
   * Determines if projects exists and has a version string.
   *
   * @param string $project_name
   *   The project.
   *
   * @return bool
   *   TRUE if extension exists, else FALSE.
   */
  protected function isValidProject(string $project_name) {
    try {
      $project = $this->getProject($project_name);
      return !empty($project['info']['version']);
    }
    catch (\UnexpectedValueException $exception) {
      $this->logger->error($exception->getMessage());
      return FALSE;
    }
  }

  /**
   * Determines if the Psa versions match for the installed version of project.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement.
   *
   * @return bool
   *   TRUE if security announcement matches the installed version of the
   *   project, otherwise FALSE.
   *
   * @throws \UnexpectedValueException
   *   Thrown by \Composer\Semver\VersionParser::parseConstraints() if the
   *   constraint string is not valid.
   */
  protected function matchesInstalledVersion(SecurityAnnouncement $sa) {
    $parser = new VersionParser();
    $versions = $sa->getProjectType() === 'core' ? $sa->getInsecureVersions() : $this->getContribVersions($sa->getInsecureVersions());

    try {
      $installed_constraint = $parser->parseConstraints($this->getInstalledVersion($sa));
    }
    catch (\UnexpectedValueException $exception) {
      // If the installed version can not be parsed assume it matches to avoid
      // not returning a critical PSA.
      return TRUE;
    }

    foreach ($versions as $version) {
      try {
        if ($parser->parseConstraints($version)->matches($installed_constraint)) {
          return TRUE;
        }
      }
      catch (\UnexpectedValueException $exception) {
        // If an individual constraint is throws an exception continue to check
        // the other versions.
        continue;
      }
    }
    return FALSE;
  }

  /**
   * Returns a message that links the security announcement.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The PSA or SA message.
   */
  protected function message(SecurityAnnouncement $sa) {
    return new FormattableMarkup('<a href=":url">:message</a>', [
      ':message' => $sa->getTitle(),
      ':url' => $sa->getLink(),
    ]);
  }

  /**
   * Gets the contrib version to use for comparisons.
   *
   * @param $versions
   *   Contrib project versions.
   *
   * @return string[]
   */
  private function getContribVersions($versions) {
    $versions = array_filter(array_map(static function ($version) {
      $version_array = explode('-', $version, 2);
      if ($version_array && $version_array[0] === \Drupal::CORE_COMPATIBILITY) {
        return isset($version_array[1]) ? $version_array[1] : NULL;
      }
      if (count($version_array) === 1) {
        return $version_array[0];
      }
      if (count($version_array) === 2 && $version_array[1] === 'dev') {
        return $version;
      }
    }, $versions));
    return $versions;
  }

  /**
   * Gets the currently installed version of a project.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement.
   *
   * @return string
   *   The currently installed version.
   */
  private function getInstalledVersion(SecurityAnnouncement $sa) {
    if ($sa->getProjectType() === 'core') {
      return \Drupal::VERSION;
    }
    $project = $this->getProject($sa->getProject());
    $extension_version = $project['info']['version'];
    $version_array = explode('-', $extension_version, 2);
    return isset($version_array[1]) && $version_array[1] !== 'dev' ? $version_array[1] : $extension_version;
  }

  /**
   * @return array
   */
  protected function getProject($project_name): array {
    static $projects = [];
    if (empty($projects)) {
      $projects = $this->updateManager->getProjects();
    }

    return $projects[$project_name] ?? [];
  }

}

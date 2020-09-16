<?php

namespace Drupal\update\Psa;

use Composer\Semver\VersionParser;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\update\ProjectInfoTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/**
 * Service class to get Public Service Messages.
 */
class UpdatesPsa implements UpdatesPsaInterface {
  use StringTranslationTrait;
  use DependencySerializationTrait;
  use ProjectInfoTrait;

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
   * @param \Drupal\Core\Extension\ExtensionList $module_list
   *   The module extension list.
   * @param \Drupal\Core\Extension\ExtensionList $profile_list
   *   The profile extension list.
   * @param \Drupal\Core\Extension\ExtensionList $theme_list
   *   The theme extension list.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, TimeInterface $time, Client $client, ExtensionList $module_list, ExtensionList $profile_list, ExtensionList $theme_list, LoggerInterface $logger) {
    $this->config = $config_factory->get('update.settings');
    $this->cache = $cache;
    $this->time = $time;
    $this->httpClient = $client;
    $this->logger = $logger;
    $this->setExtensionLists($module_list, $theme_list, $profile_list);
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
        return [$this->t('Drupal PSA endpoint :url is unreachable.', [':url' => $psa_endpoint])];
      }
    }

    try {
      $json_payload = json_decode($response, TRUE);
      if ($json_payload !== NULL) {
        foreach ($json_payload as $json) {
          $sa = SecurityAnnouncement::createFromArray($json);
          if ($sa->getProjectType() !== 'core' && !$this->isValidExtension($sa->getProjectType(), $sa->getProject())) {
            continue;
          }
          if ($sa->isPsa() || $this->matchesInstalledVersion($sa)) {
            $messages[] = $this->message($sa);
          }
        }
      }
      else {
        $this->logger->error('Drupal PSA JSON is malformed: @response', ['@response' => $response]);
        $messages[] = $this->t('Drupal PSA JSON is malformed.');
      }

    }
    catch (\UnexpectedValueException $exception) {
      $this->logger->error($exception->getMessage());
      $messages[] = $this->t('Drupal PSA endpoint service is malformed.');
    }

    return $messages;
  }

  /**
   * Determines if extension exists and has a version string.
   *
   * @param string $extension_type
   *   The extension type i.e. module, theme, profile.
   * @param string $project_name
   *   The project.
   *
   * @return bool
   *   TRUE if extension exists, else FALSE.
   */
  protected function isValidExtension(string $extension_type, string $project_name) {
    try {
      $extension_list = $this->getExtensionList($extension_type);
      return $extension_list->exists($project_name) && !empty($extension_list->getAllAvailableInfo()[$project_name]['version']);
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
   */
  protected function matchesInstalledVersion(SecurityAnnouncement $sa) {
    $versions = $sa->getProjectType() === 'core' ? $sa->getInsecureVersions() : $this->getContribVersions($sa->getInsecureVersions());
    $version_string = implode('||', $versions);
    if (empty($version_string)) {
      return FALSE;
    }
    $parser = new VersionParser();
    $psa_constraint = $parser->parseConstraints($version_string);
    $installed_constraint = $parser->parseConstraints($this->getInstalledVersion($sa));
    return $psa_constraint->matches($installed_constraint);
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
    $extension_version = $this->getExtensionList($sa->getProjectType())->getAllAvailableInfo()[$sa->getProject()]['version'];
    $version_array = explode('-', $extension_version, 2);
    return isset($version_array[1]) && $version_array[1] !== 'dev' ? $version_array[1] : $extension_version;
  }

}

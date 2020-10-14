<?php

namespace Drupal\update\Services;

use Composer\Semver\VersionParser;
use Drupal\update\ProjectInfoTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Class UpdatesPsa.
 *
 * Get Public Service Messages when it is available.
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
   * UpdatesPsa constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
    * @param \Drupal\Core\Extension\ExtensionList $moduleList
   *   The module extension list.
   * @param \Drupal\Core\Extension\ExtensionList $profileList
   *   The profile extension list.
   * @param \Drupal\Core\Extension\ExtensionList $themeList
   *   The theme extension list.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, TimeInterface $time, Client $client, ExtensionList $moduleList, ExtensionList $profileList, ExtensionList $themeList, LoggerInterface $logger) {
    $this->config = $config_factory->get('update_psa.settings');
    $this->cache = $cache;
    $this->time = $time;
    $this->httpClient = $client;
    $this->logger = $logger;
    $this->setExtensionLists($moduleList, $themeList, $profileList);
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicServiceMessages() {
    $messages = [];
    if (!$this->config->get('enable_psa')) {
      return $messages;
    }

    if ($cache = $this->cache->get('updates_psa')) {
      $response = $cache->data;
    }
    else {
      $psa_endpoint = $this->config->get('psa_endpoint');
      try {
        $response = $this->httpClient->get($psa_endpoint)
          ->getBody()
          ->getContents();
        $this->cache->set('updates_psa', $response, $this->time->getCurrentTime() + $this->config->get('check_frequency'));
      }
      catch (TransferException $exception) {
        $this->logger->error($exception->getMessage());
        return [$this->t('Drupal PSA endpoint :url is unreachable.', [':url' => $psa_endpoint])];
      }
    }

    try {
      $json_payload = json_decode($response, FALSE);
      if ($json_payload !== NULL) {
        foreach ($json_payload as $json) {
          if ($json->is_psa && ($json->type === 'core' || $this->isValidExtension($json->type, $json->project))) {
            $messages[] = $this->message($json->title, $json->link);
          }
          elseif ($json->type === 'core') {
            $this->parseConstraints($messages, $json, \Drupal::VERSION);
          }
          elseif ($this->isValidExtension($json->type, $json->project)) {
            $this->contribParser($messages, $json);
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
   * Determine if extension exists and has a version string.
   *
   * @param string $extension_type
   *   The extension type i.e. module, theme, profile.
   * @param string $project_name
   *   The project.
   *
   * @return bool
   *   TRUE if extension exists, else FALSE.
   */
  protected function isValidExtension($extension_type, $project_name) {
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
   * Parse contrib project JSON version strings.
   *
   * @param array $messages
   *   The messages array.
   * @param object $json
   *   The JSON object.
   */
  protected function contribParser(array &$messages, $json) {
    $extension_version = $this->getExtensionList($json->type)->getAllAvailableInfo()[$json->project]['version'];
    $json->insecure = array_filter(array_map(static function ($version) {
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
    }, $json->insecure));
    $version_array = explode('-', $extension_version, 2);
    $extension_version = isset($version_array[1]) && $version_array[1] !== 'dev' ? $version_array[1] : $extension_version;
    $this->parseConstraints($messages, $json, $extension_version);
  }

  /**
   * Compare versions and add a message, if appropriate.
   *
   * @param array $messages
   *   The messages array.
   * @param object $json
   *   The JSON object.
   * @param string $current_version
   *   The current extension version.
   *
   * @throws \UnexpectedValueException
   */
  protected function parseConstraints(array &$messages, $json, $current_version) {
    $version_string = implode('||', $json->insecure);
    if (empty($version_string)) {
      return;
    }
    $parser = new VersionParser();
    $psa_constraint = $parser->parseConstraints($version_string);
    $contrib_constraint = $parser->parseConstraints($current_version);
    if ($psa_constraint->matches($contrib_constraint)) {
      $messages[] = $this->message($json->title, $json->link);
    }
  }

  /**
   * Return a message.
   *
   * @param string $title
   *   The title.
   * @param string $link
   *   The link.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The PSA or SA message.
   */
  protected function message($title, $link) {
    return new FormattableMarkup('<a href=":url">:message</a>', [
      ':message' => $title,
      ':url' => $link,
    ]);
  }

}

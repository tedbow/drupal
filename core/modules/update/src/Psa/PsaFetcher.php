<?php

namespace Drupal\update\Psa;

use Composer\Semver\VersionParser;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\ProjectInfo;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

/**
 * Defines a service class to get Public Service Messages.
 */
class PsaFetcher implements PsaFetcherInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  protected const MALFORMED_JSON_EXCEPTION_CODE = 1000;

  /**
   * This 'update.settings' configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Update key/value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $tempStore;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The extension lists.
   *
   * @var \Drupal\Core\Extension\ExtensionList[]
   */
  protected $extensionLists;

  /**
   * Constructs a new PsaFetcher object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_factory
   *   The expirable key/value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   The theme extension list.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_list
   *   The profile extension list.
   */
  public function __construct(ConfigFactoryInterface $config_factory, KeyValueExpirableFactoryInterface $key_value_factory, TimeInterface $time, Client $client, ModuleExtensionList $module_list, ThemeExtensionList $theme_list, ProfileExtensionList $profile_list) {
    $this->config = $config_factory->get('update.settings');
    $this->tempStore = $key_value_factory->get('update');
    $this->time = $time;
    $this->httpClient = $client;
    $this->extensionLists['module'] = $module_list;
    $this->extensionLists['theme'] = $theme_list;
    $this->extensionLists['profile'] = $profile_list;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicServiceMessages() : array {
    $messages = [];

    $response = $this->tempStore->get('psa_response');
    if (!$response) {
      $psa_endpoint = $this->config->get('psa.endpoint');
      $response = (string) $this->httpClient->get($psa_endpoint)->getBody();
      $this->tempStore->setWithExpire('psa_response', $response, $this->config->get('psa.check_frequency'));
    }

    $json_payload = json_decode($response, TRUE);
    if ($json_payload !== NULL) {
      foreach ($json_payload as $json) {
        try {
          $sa = SecurityAnnouncement::createFromArray($json);
        }
        catch (\UnexpectedValueException $unexpected_value_exception) {
          throw new \UnexpectedValueException($unexpected_value_exception->getMessage(), static::MALFORMED_JSON_EXCEPTION_CODE);
        }

        if ($sa->getProjectType() !== 'core' && !$this->getProjectVersion($sa)) {
          continue;
        }
        if ($sa->isPsa() || $this->matchesInstalledVersion($sa)) {
          $messages[] = $this->message($sa);
        }
      }
    }
    else {
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
   *   Whether to re-throw exceptions that are not expected.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The message to display.
   *
   * @throws \Exception
   *    Throw if the exception is not expected.
   */
  public static function getErrorMessageFromException(\Exception $exception, bool $throw_unexpected_exceptions = TRUE) {
    if ($exception instanceof TransferException) {
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
   * Determines if the PSA versions match for the installed version of project.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement.
   *
   * @return bool
   *   TRUE if security announcement matches the installed version of the
   *   project; otherwise FALSE.
   *
   * @throws \UnexpectedValueException
   *   Thrown by \Composer\Semver\VersionParser::parseConstraints() if the
   *   constraint string is not valid.
   */
  protected function matchesInstalledVersion(SecurityAnnouncement $sa) : bool {
    $parser = new VersionParser();
    $versions = $sa->getProjectType() === 'core' ? $sa->getInsecureVersions() : $this->getContribVersions($sa->getInsecureVersions());

    try {
      $installed_constraint = $parser->parseConstraints($this->getExistingVersionConstraint($sa));
    }
    catch (\UnexpectedValueException $exception) {
      // If the installed version cannot be parsed, assume it matches to avoid
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
   *   The security announcement.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The PSA or SA message.
   */
  protected function message(SecurityAnnouncement $sa) : FormattableMarkup {
    return new FormattableMarkup('<a href=":url">:message</a>', [
      ':message' => $sa->getTitle(),
      ':url' => $sa->getLink(),
    ]);
  }

  /**
   * Gets the contrib version to use for comparisons.
   *
   * @param string[] $versions
   *   Contrib project versions.
   *
   * @return string[]
   *   The versions that can be used for comparison.
   */
  private function getContribVersions(array $versions) : array {
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
   * Gets the currently existing project version as a constraint string.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement.
   *
   * @return string
   *   The existing project version as constraint string.
   */
  private function getExistingVersionConstraint(SecurityAnnouncement $sa) : string {
    if ($sa->getProjectType() === 'core') {
      return \Drupal::VERSION;
    }
    $project_version = $this->getProjectVersion($sa);
    $version_array = explode('-', $project_version, 2);
    return isset($version_array[1]) && $version_array[1] !== 'dev' ? $version_array[1] : $project_version;
  }

  /**
   * Gets the project version.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement.
   *
   * @return string
   *   The project version or an empty string if the project is not available.
   */
  protected function getProjectVersion(SecurityAnnouncement $sa): string {
    static $extensions = [];
    $project_type = $sa->getProjectType();
    if (!isset($extensions[$project_type])) {
      $extensions[$project_type] = $this->extensionLists[$project_type]->getList();
    }
    $project_info = new ProjectInfo();
    foreach ($extensions[$project_type] as $extension) {
      if ($project_info->getProjectName($extension) === $sa->getProject()) {
        return $extension->info['version'] ?? '';
      }
    }
    return '';
  }

}

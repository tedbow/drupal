<?php

namespace Drupal\update\Psa;

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
 * Defines a service class to get Public Service Announcements.
 */
class PsaFetcher {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  protected const MALFORMED_JSON_EXCEPTION_CODE = 1000;

  /**
   * The 'update.settings' configuration.
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
   * The update key/value store.
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
   * The extension lists keyed by extension type.
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
   * Gets public service messages.
   *
   * @return \Drupal\Component\Render\FormattableMarkup[]
   *   A array of translatable strings.
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

        if ($sa->getProjectType() !== 'core' && !$this->getProjectExistingVersion($sa)) {
          continue;
        }
        if ($sa->isPsa() || $this->matchesExistingVersion($sa)) {
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
   * Determines if the PSA versions match for the existing version of project.
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
  protected function matchesExistingVersion(SecurityAnnouncement $sa) : bool {
    if ($existing_version = $this->getProjectExistingVersion($sa)) {
      return in_array($existing_version, $sa->getInsecureVersions(), TRUE);
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
   * Gets the project version.
   *
   * @param \Drupal\update\Psa\SecurityAnnouncement $sa
   *   The security announcement.
   *
   * @return string
   *   The project version or an empty string if the project is not available.
   */
  protected function getProjectExistingVersion(SecurityAnnouncement $sa): string {
    $project_type = $sa->getProjectType();
    if ($project_type === 'core') {
      return \Drupal::VERSION;
    }
    if (!isset($this->extensionLists[$project_type])) {
      return '';
    }
    $project_info = new ProjectInfo();
    foreach ($this->extensionLists[$project_type]->getList() as $extension) {
      if ($project_info->getProjectName($extension) === $sa->getProject()) {
        return $extension->info['version'] ?? '';
      }
    }
    return '';
  }

}

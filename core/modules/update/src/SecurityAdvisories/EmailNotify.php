<?php

namespace Drupal\update\SecurityAdvisories;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides an service to send email notifications for Security Advisories.
 */
class EmailNotify {

  use StringTranslationTrait;

  private const LAST_MESSAGES_STATE_KEY = 'update_sa.last_messages_hash';

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The Security Advisory fetcher service.
   *
   * @var \Drupal\update\SecurityAdvisories\SecurityAdvisoriesFetcher
   */
  protected $psaFetcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * EmailNotify constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\update\SecurityAdvisories\SecurityAdvisoriesFetcher $psa_fetcher
   *   The Security Advisory fetcher service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(MailManagerInterface $mail_manager, SecurityAdvisoriesFetcher $psa_fetcher, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, StateInterface $state, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, LoggerInterface $logger) {
    $this->mailManager = $mail_manager;
    $this->psaFetcher = $psa_fetcher;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->state = $state;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->logger = $logger;
  }

  /**
   * Send notification when Security Advisories are available.
   */
  public function send(): void {
    $notify_emails = $this->configFactory->get('update.settings')->get('notification.emails');
    if (!$notify_emails) {
      return;
    }
    try {
      $messages = $this->psaFetcher->getSecurityAdvisoriesMessages();
    }
    catch (\Exception $exception) {
      $this->logger->error(
        'Unable to send notification email because of error retrieving PSA feed: %error',
        ['%error' => SecurityAdvisoriesFetcher::getErrorMessageFromException($exception, FALSE)]
      );
      return;
    }

    if (!$messages) {
      return;
    }

    $messages_hash = hash('sha256', serialize($messages));
    // Return if the messages are the same as the last messages sent.
    if ($messages_hash === $this->state->get(static::LAST_MESSAGES_STATE_KEY)) {
      return;
    }

    $params['subject'] = new PluralTranslatableMarkup(
      count($messages),
      '@count urgent security announcement requires your attention for @site_name',
      '@count urgent security announcements require your attention for @site_name',
      ['@site_name' => $this->configFactory->get('system.site')->get('name')]
    );
    $params['body'] = [
      '#theme' => 'update_psa_notify',
      '#messages' => $messages,
    ];
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $user_storage = $this->entityTypeManager->getStorage('user');
    foreach ($notify_emails as $notify_email) {
      /** @var \Drupal\user\UserInterface[] $users */
      $users = $user_storage->loadByProperties(['mail' => $notify_email]);
      $params['langcode'] = $users ? (reset($users))->getPreferredLangcode() : $default_langcode;
      $this->mailManager->mail('update', 'psa_notify', $notify_email, $params['langcode'], $params);
    }
    $this->state->set(static::LAST_MESSAGES_STATE_KEY, $messages_hash);
  }

}

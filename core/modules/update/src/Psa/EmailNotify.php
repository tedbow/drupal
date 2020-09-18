<?php

namespace Drupal\update\Psa;

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
 * An implementation of the NotifyInterface which uses email for notification.
 */
class EmailNotify implements NotifyInterface {
  use StringTranslationTrait;

  private const LAST_MESSAGES_STATE_KEY = 'update_psa.last_messages_hash';

  /**
   * Mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The automatic updates service.
   *
   * @var \Drupal\update\Psa\UpdatesPsaInterface
   */
  protected $updatesPsa;

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
   * Entity type manager.
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
   * @param \Drupal\update\Psa\UpdatesPsaInterface $updates_psa
   *   The automatic updates service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(MailManagerInterface $mail_manager, UpdatesPsaInterface $updates_psa, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, StateInterface $state, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, LoggerInterface $logger) {
    $this->mailManager = $mail_manager;
    $this->updatesPsa = $updates_psa;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->state = $state;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function send() {
    $notify_emails = $this->configFactory->get('update.settings')->get('notification.emails');
    // Don't send mail if notifications are disabled.
    if (!$notify_emails || !$this->configFactory->get('update.settings')->get('psa.notify')) {
      return;
    }
    try {
      $messages = $this->updatesPsa->getPublicServiceMessages();
    }
    catch (\Exception $exception) {
      $this->logger->error($this->t(
        'Unable to send notification email because of error retrieving PSA feed: @error'),
        ['@error' => UpdatesPsa::getErrorMessageFromException($exception, FALSE)]
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
      '@count urgent Drupal announcement requires your attention for @site_name',
      '@count urgent Drupal announcements require your attention for @site_name',
      ['@site_name' => $this->configFactory->get('system.site')->get('name')]
    );
    $params['body'] = [
      '#theme' => 'updates_psa_notify',
      '#messages' => $messages,
    ];
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $params['langcode'] = $default_langcode;
    foreach ($notify_emails as $notify_email) {
      $this->doSend($notify_email, $params);
    }
    $this->state->set(static::LAST_MESSAGES_STATE_KEY, $messages_hash);
  }

  /**
   * Composes and send the email message.
   *
   * @param string $email
   *   The email address where the message will be sent.
   * @param array $params
   *   Parameters to build the email.
   */
  protected function doSend(string $email, array $params) {
    /** @var \Drupal\user\UserInterface[] $users */
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    if ($user = reset($users)) {
      $params['langcode'] = $user->getPreferredLangcode();
      $this->mailManager->mail('update', 'psa_notify', $email, $params['langcode'], $params);
    }
  }

}

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

/**
 * An implementation of the NotifyInterface which uses email for notification.
 */
class EmailNotify implements NotifyInterface {
  use StringTranslationTrait;

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
   */
  public function __construct(MailManagerInterface $mail_manager, UpdatesPsaInterface $updates_psa, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, StateInterface $state, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    $this->mailManager = $mail_manager;
    $this->updatesPsa = $updates_psa;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->state = $state;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function send() {
    // Don't send mail if notifications are disabled.
    if (!$this->configFactory->get('update.settings')->get('psa.notify')) {
      return;
    }
    $messages = $this->updatesPsa->getPublicServiceMessages();
    if (!$messages) {
      return;
    }
    $notify_emails = $this->configFactory->get('update.settings')->get('notification.emails');
    if (!empty($notify_emails)) {
      $frequency = $this->configFactory->get('update.settings')->get('psa.check_frequency');
      $last_check = $this->state->get('update_psa.notify_last_check') ?: 0;
      if (($this->time->getRequestTime() - $last_check) > $frequency) {
        $this->state->set('update_psa.notify_last_check', $this->time->getRequestTime());

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
      }
    }
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

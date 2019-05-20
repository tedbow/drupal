<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\LayoutBuilderPluginTranslationFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block plugin form for translatable settings in the Layout Builder.
 *
 * This form only allows translation of the 'label' string if it is displayed in
 * the Layout Builder. If a plugin needs other configuration options it should
 * provide its own 'layout_builder_translation' plugin form.
 *
 * @internal
 *   Form classes are internal.
 */
class BlockPluginTranslationForm extends PluginFormBase implements ContainerInjectionInterface, LayoutBuilderPluginTranslationFormInterface {

  use StringTranslationTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * The current language code.
   *
   * @var string
   */
  protected $currentLangcode;

  /**
   * The translated configuration for the plugin.
   *
   * @var array
   */
  protected $translatedConfiguration;

  /**
   * BlockPluginTranslationForm constructor.
   *
   * @param string $current_langcode
   *   The current language code.
   */
  public function __construct($current_langcode) {
    $this->currentLangcode = $current_langcode;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->plugin->getConfiguration();
    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => isset($this->translatedConfiguration['label']) ? $this->translatedConfiguration['label'] : $configuration['label'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslatedConfiguration(array $translated_configuration) {
    $this->translatedConfiguration = $translated_configuration;
  }

}

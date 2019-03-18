<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block plugin form for translatable settings in the Layout Builder.
 */
class BlockPluginTranslationForm extends PluginFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * The current language code.
   *
   * @var string
   */
  protected $currentLangcode;

  /**
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
      $container->get('language_manager')->getCurrentLanguage()->getId()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($this->plugin instanceof ConfigurableInterface) {
      $configuration = $this->plugin->getConfiguration();
      $form['label'] = [
        '#title' => $this->t('Label'),
        '#type' => 'textfield',
        '#default_value' => isset($this->translatedConfiguration['label']) ? $this->translatedConfiguration['label'] : $configuration['label'],
        '#required' => TRUE,
      ];
    }
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
    /*if ($this->plugin instanceof ConfigurableInterface) {
      $configuration = $this->plugin->getConfiguration();
      $configuration['layout_builder_translations'][$this->currentLangcode]['label'] = $form_state->getValue('translated_label');
      $this->plugin->setConfiguration($configuration);
    }*/
  }

  public function setTranslatedConfiguration(array $translated_configuration) {
    $this->translatedConfiguration = $translated_configuration;

  }

}

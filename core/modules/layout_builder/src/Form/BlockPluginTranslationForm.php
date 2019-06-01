<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\LayoutBuilderPluginTranslationFormInterface;

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
class BlockPluginTranslationForm extends PluginFormBase implements LayoutBuilderPluginTranslationFormInterface {

  use StringTranslationTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * The translated configuration for the plugin.
   *
   * @var array
   */
  protected $translatedConfiguration;

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
   *
   * We only saving the label translation the label the form values will be
   * saved in \Drupal\layout_builder\Form\TranslateBlockForm::submitForm().
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

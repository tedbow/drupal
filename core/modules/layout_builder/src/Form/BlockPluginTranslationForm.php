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
      $form['translated_label'] = [
        '#title' => $this->t('Label'),
        '#type' => 'textfield',
        '#default_value' => isset($configuration['layout_builder_translations'][$this->currentLangcode]['label']) ? $configuration['layout_builder_translations'][$this->currentLangcode]['label'] : $configuration['label'],
        '#required' => TRUE,
      ];
      if ($this->plugin instanceof ContextAwarePluginInterface) {
        $form['context_mapping'] = $this->addContextAssignmentElement($this->plugin, $this->plugin->getContexts());
      }
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
    if ($this->plugin instanceof ConfigurableInterface) {
      $configuration = $this->plugin->getConfiguration();
      $configuration['layout_builder_translations'][$this->currentLangcode]['label'] = $form_state->getValue('translated_label');
      $this->plugin->setConfiguration($configuration);
    }
  }

}

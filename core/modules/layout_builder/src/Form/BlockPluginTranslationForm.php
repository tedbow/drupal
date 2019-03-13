<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block plugin form for translatable settings in the Layout Builder.
 */
class BlockPluginTranslationForm extends PluginFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  protected $current_language;

  /**
   * BlockPluginTranslationForm constructor.
   *
   * @param $current_language
   */
  public function __construct($current_language) {
    $this->current_language = $current_language;
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
       '#title' => $this->t('Translated label'),
       '#type' => 'textfield',
       '#default_value' => isset($configuration['layout_builder_translations'][$this->current_language]['label']) ? $configuration['layout_builder_translations'][$this->current_language]['label'] : $configuration['label'],
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
    if ($this->plugin instanceof ConfigurableInterface) {
      $configuration = $this->plugin->getConfiguration();
      $configuration['layout_builder_translations'][$this->current_language]['label']  = $form_state->getValue('translated_label');
      $this->plugin->setConfiguration($configuration);
    }
  }

  /**
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   */
  protected function getConfigureForm() {
    /** @var \Drupal\Core\Plugin\PluginFormFactoryInterface $form_factory */
    $form_factory = \Drupal::service('plugin_form.factory');
    return $form_factory->createInstance($this->plugin, 'configure');
  }

}

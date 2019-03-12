<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Render\Element;

/**
 * Provides a block plugin form for translatable settings in the Layout Builder.
 */
class BlockPluginTranslationForm extends PluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($this->plugin instanceof ConfigurableInterface) {
      $configure_form = $this->getConfigureForm()->buildConfigurationForm($form, $form_state);
      foreach (Element::children($configure_form) as $key) {
        if ($key !== 'label' && $key !== 'label_display') {
          $configure_form[$key]['#access'] = FALSE;
        }
      }
      return $configure_form;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->getConfigureForm()->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->getConfigureForm()->submitConfigurationForm($form, $form_state);
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

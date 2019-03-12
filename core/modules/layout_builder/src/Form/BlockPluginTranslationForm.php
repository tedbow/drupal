<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Plugin\PluginFormInterface;

class BlockPluginTranslationForm extends PluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($this->plugin instanceof ConfigurableInterface) {
      $configuration = $this->plugin->getConfiguration();
      if (!empty($configuration['label_display']) && !empty($configuration['label'])) {
        return [
          '#markup' => 'whats up?',
        ];
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement validateConfigurationForm() method.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitConfigurationForm() method.
  }
}

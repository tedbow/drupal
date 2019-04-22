<?php


namespace Drupal\layout_builder\Form;


use Drupal\Component\Plugin\ConfigurableInterface;

trait BlockPluginTranslationFormTrait {

  /**
   * Builds the label configuration form.
   *
   * @param array $form
   *
   * @return array
   */
  protected function buildLabelConfigurationForm(array $form) {
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
  public function setTranslatedConfiguration(array $translated_configuration) {
    $this->translatedConfiguration = $translated_configuration;
  }

}

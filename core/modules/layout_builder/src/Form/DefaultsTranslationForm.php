<?php

namespace Drupal\layout_builder\Form;

use Drupal\config_translation\Form\ConfigTranslationFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultsTranslationForm extends ConfigTranslationFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'defaults_layout_builder_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   *
   * Builds configuration form with metadata and values from the source
   * language.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   (optional) The route match.
   * @param string $plugin_id
   *   (optional) The plugin ID of the mapper.
   * @param string $langcode
   *   (optional) The language code of the language the form is adding or
   *   editing.
   *
   * @return array
   *   The form structure.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws an exception if the language code provided as a query parameter in
   *   the request does not match an active language.
   */
  public function buildForm(array $form, FormStateInterface $form_state, RouteMatchInterface $route_match = NULL, $plugin_id = NULL, $langcode = NULL, SectionStorageInterface $section_storage = NULL) {
    /** @var \Drupal\config_translation\ConfigMapperInterface $mapper */
    $mapper = $this->configMapperManager->createInstance($plugin_id);
    $mapper->populateFromRouteMatch($route_match);

    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      throw new NotFoundHttpException();
    }

    $this->mapper = $mapper;
    $this->language = $language;

    // ConfigTranslationFormAccess will not grant access if this raises an
    // exception, so we can call this without a try-catch block here.
    $langcode = $this->mapper->getLangcode();

    $this->sourceLanguage = $this->languageManager->getLanguage($langcode);

    // Get base language configuration to display in the form before setting the
    // language to use for the form. This avoids repetitively settings and
    // resetting the language to get original values later.
    $this->baseConfigData = $this->mapper->getConfigData();

    // Set the translation target language on the configuration factory.
    $original_language = $this->languageManager->getConfigOverrideLanguage();
    $this->languageManager->setConfigOverrideLanguage($this->language);

    // Add some information to the form state for easier form altering.
    $form_state->set('config_translation_mapper', $this->mapper);
    $form_state->set('config_translation_language', $this->language);
    $form_state->set('config_translation_source_language', $this->sourceLanguage);

    $form['layout_builder'] = [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
    ];
    $form['actions'] = [
      '#type' => 'container',
      '#weight' => -1000,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save layout'),
    ];
    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_data = $this->mapper->getConfigData();
    $name = array_keys($config_data)[0];
    // Set configuration values based on form submission and source values.
    $base_config = $this->configFactory()->getEditable($name);
    $section_arrays = $base_config->get('third_party_settings.layout_builder.sections');
    foreach ($section_arrays as $section_array) {
      $section = Section::fromArray($section_array);
    }

    $config_translation = $this->languageManager->getLanguageConfigOverride($this->language->getId(), $name);
  }

}

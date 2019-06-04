<?php

namespace Drupal\layout_builder\Form;

use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\config_translation\Form\ConfigTranslationFormBase;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultsTranslationForm extends ConfigTranslationFormBase {

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(TypedConfigManagerInterface $typed_config_manager, ConfigMapperManagerInterface $config_mapper_manager, ConfigurableLanguageManagerInterface $language_manager, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    parent::__construct($typed_config_manager, $config_mapper_manager, $language_manager);
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.typed'),
      $container->get('plugin.manager.config_translation.mapper'),
      $container->get('language_manager'),
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'defaults_layout_builder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RouteMatchInterface $route_match = NULL, $plugin_id = NULL, $langcode = NULL, SectionStorageInterface $section_storage = NULL) {
    $this->sectionStorage = $section_storage;
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

    $names = $this->mapper->getConfigNames();
    $translation_config = $this->configFactory()->get($names[0])->get();

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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->sectionStorage->save();
    $this->layoutTempstoreRepository->delete($this->sectionStorage);
    $this->messenger()->addMessage($this->t('The layout translation has been saved.'));
    $form_state->setRedirectUrl($this->sectionStorage->getRedirectUrl());
  }

}

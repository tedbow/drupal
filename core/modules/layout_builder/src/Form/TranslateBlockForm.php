<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\layout_builder\LayoutBuilderPluginTranslationFormInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TranslatableSectionStorageInterface;

/**
 * Provides a form to translate a block in the Layout Builder.
 */
class TranslateBlockForm extends ConfigureBlockFormBase {

  /**
   * {@inheritdoc}
   */
  protected function submitLabel() {
    return $this->t('Translate');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_block_translation';
  }

  /**
   * Builds the block form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string $uuid
   *   The UUID of the block being updated.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    return $this->doBuildForm($form, $form_state, $section_storage, $delta, $component);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginForm(BlockPluginInterface $block) {
    if ($block instanceof PluginWithFormsInterface && $this->sectionStorage instanceof TranslatableSectionStorageInterface) {
      $plugin_form = $this->pluginFormFactory->createInstance($block, 'layout_builder_translation');
      if ($plugin_form instanceof LayoutBuilderPluginTranslationFormInterface) {
        $plugin_form->setTranslatedConfiguration($this->sectionStorage->getTranslatedComponentConfiguration($this->uuid));
      }
      return $plugin_form;
    }
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Call the plugin submit handler.
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->getPluginForm($this->block)->submitConfigurationForm($form, $subform_state);

    $settings = $subform_state->getValues();
    /** @var \Drupal\layout_builder\TranslatableSectionStorageInterface $section_storage */
    $section_storage = $this->sectionStorage;
    $section_storage->setTranslatedComponentConfiguration($this->uuid, $settings);

    $this->layoutTempstoreRepository->set($this->sectionStorage);
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl());
  }

}

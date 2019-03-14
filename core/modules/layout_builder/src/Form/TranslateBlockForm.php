<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\layout_builder\SectionStorageInterface;

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
    if ($block instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($block, 'layout_builder_translation');
    }
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    if ($subform_state->hasValue('context_mapping')) {
      $mapping = $subform_state->getValue('context_mapping');
      // @todo(in this issue) Should really have to switch the context mapping here?
      if (isset($mapping['entity']) && $mapping['entity'] === 'entity') {
        $mapping['entity'] = 'layout_builder.entity';
      }
      $subform_state->setValue('context_mapping', $mapping);
    }
  }

}

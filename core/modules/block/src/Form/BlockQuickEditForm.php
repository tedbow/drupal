<?php

namespace Drupal\block\Form;


use Drupal\block\BlockForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Quick Edit form for editing blocks.
 */
class BlockQuickEditForm extends BlockForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $advanced_elements = ['theme', 'region', 'visibility', 'id'];
    foreach ($advanced_elements as $advanced_element) {
      unset($form[$advanced_element]);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $form_id = $this->entity->getEntityTypeId();
    if ($this->entity->getEntityType()->hasKey('bundle')) {
      $form_id .= '_' . $this->entity->bundle();
    }
    return 'quick_edit_' . $form_id . '_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // The Block Entity form puts all block plugin form elements in the
    // settings form element, so just pass that to the block for validation.
    $settings = (new FormState())->setValues($form_state->getValue('settings'));
    // Call the plugin validate handler.
    $this->entity->getPlugin()->validateConfigurationForm($form, $settings);
    // Update the original form values.
    $form_state->setValue('settings', $settings->getValues());
  }

}

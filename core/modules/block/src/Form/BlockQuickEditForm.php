<?php

namespace Drupal\block\Form;


use Drupal\block\BlockForm;
use Drupal\Component\Serialization\Json;
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
    // Change delete link into a modal.
    $form['actions']['delete']['#attributes']['class'][] = 'use-ajax';
    $form['actions']['delete']['#attributes']['data-dialog-type'] = 'modal';
    $form['actions']['delete']['#attributes']['data-dialog-options'] = Json::encode(['width' => 700,]);

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

}

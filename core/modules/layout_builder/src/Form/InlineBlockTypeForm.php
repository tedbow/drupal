<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class InlineBlockTypeForm.
 */
class InlineBlockTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $inline_block_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $inline_block_type->label(),
      '#description' => $this->t("Label for the Inline block type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $inline_block_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\layout_builder\Entity\InlineBlockType::load',
      ],
      '#disabled' => !$inline_block_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $inline_block_type = $this->entity;
    $status = $inline_block_type->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Inline block type.', [
          '%label' => $inline_block_type->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Inline block type.', [
          '%label' => $inline_block_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($inline_block_type->toUrl('collection'));
  }

}

<?php

namespace Drupal\block\Form;

use Drupal\block\BlockForm;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
    $modal_attributes = [
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode(['width' => 700]),
      'class' => 'use-ajax',
    ];
    // Change delete link into a modal.
    $form['actions']['delete']['#attributes'] = array_merge_recursive($form['actions']['delete']['#attributes'], $modal_attributes);

    // Create link to full block form.
    $query = [];
    $advance_url = Url::fromRoute(
      'entity.block.edit_form',
      [
        'block' => $this->entity->id(),
      ]
    );

    if ($destination = $this->getRequest()->query->has('destination')) {
      $query['destination'] = $this->getRequest()->query->get('destination');
      $advance_url->setOption('query', $query);
    }

    $form['actions']['advanced'] = [
      '#type' => 'link',
      '#title' => $this->t('Advanced Options'),
      '#url' => $advance_url,
      '#attributes' => $modal_attributes,
    ];

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

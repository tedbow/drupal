<?php

namespace Drupal\outside_in\Block;

use Drupal\block\BlockForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Url;

/**
 * @todo.
 */
class BlockEntityOffCanvasForm extends BlockForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

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
    $form['advanced_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Advanced Options'),
      '#url' => $advance_url,
      '#weight' => 1000,
    ];
    // Remove the ID and region elements.
    unset($form['id'], $form['region']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildVisibilityInterface(array $form, FormStateInterface $form_state) {
    // Do not display the visibility.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateVisibility(array $form, FormStateInterface $form_state) {
    // Intentionally empty.
  }

  /**
   * {@inheritdoc}
   */
  protected function submitVisibility(array $form, FormStateInterface $form_state) {
    // Intentionally empty.
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginForm(PluginWithFormsInterface $block) {
    return $this->pluginFormFactory->createInstance($block, 'offcanvas', 'configuration');
  }

}

<?php

namespace Drupal\block_test\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OperationAwareFormInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Provides a form that is used as a secondary form for a block.
 */
class SecondaryBlockForm implements PluginFormInterface, OperationAwareFormInterface {

  /**
   * @var string
   */
  protected $operation;

  /**
   * {@inheritdoc}
   */
  public function setOperation($operation) {
    $this->operation = $operation;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Intentionally empty.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Intentionally empty.
  }

}

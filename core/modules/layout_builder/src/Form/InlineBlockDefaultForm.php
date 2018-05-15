<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Default plugin form for 'inline_block_content' plugins.
 */
class InlineBlockDefaultForm extends InlineBlockPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->plugin->saveBlockContent();
  }

}

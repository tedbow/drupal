<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * The 'layout_builder' plugin form for 'inline_block_content' plugins.
 */
class InlineBlockLayoutBuilderForm extends InlineBlockPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->plugin->setConfigurationValue('block_serialized', serialize($this->getBlockFromBlockForm($form, $form_state)));
  }

}

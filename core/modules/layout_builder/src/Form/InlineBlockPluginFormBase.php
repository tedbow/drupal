<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;

/**
 * The base form for 'inline_block_content' plugins.
 */
class InlineBlockPluginFormBase extends PluginFormBase {
  /**
   * The block plugin.
   *
   * @var \Drupal\Core\Block\BlockPluginInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $this->plugin->buildConfigurationForm([], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->submitConfigurationForm($form, $form_state);
  }

  /**
   * Gets the content block from the block form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   The content block.
   */
  protected function getBlockFromBlockForm(array &$form, FormStateInterface $form_state) {
    // @todo Remove when https://www.drupal.org/project/drupal/issues/2948549 is closed.
    $block_form = NestedArray::getValue($form, $form_state->getTemporaryValue('block_form_parents'));
    /** @var \Drupal\block_content\BlockContentInterface $block */
    return $block_form['#block'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->validateConfigurationForm($form, $form_state);
  }

}

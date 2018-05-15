<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;

/**
 * The base form for 'inline_block_content' plugins.
 */
class InlineBlockPluginFormBase extends PluginFormBase {
  /**
   * The block plugin.
   *
   * @var \Drupal\layout_builder\Plugin\Block\InlineBlockContentBlock
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
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->validateConfigurationForm($form, $form_state);
  }

}

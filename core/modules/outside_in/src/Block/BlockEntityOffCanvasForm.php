<?php

namespace Drupal\outside_in\Block;

use Drupal\block\BlockForm;
use Drupal\block\BlockInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Render\Element\Form;

/**
 * Provides form for block instance forms when used in the off-canvas dialog.
 *
 * This form removes advanced sections of regular block form such as the
 * visibility settings, machine ID and region.
 */
class BlockEntityOffCanvasForm extends BlockForm {

  /**
   * Provides a title callback to get the block's admin label.
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function title(BlockInterface $block) {
    // @todo Wrap "Configure " in <span class="visually-hidden"></span> once
    //   https://www.drupal.org/node/2359901 is fixed.
    return $this->t('Configure @block', ['@block' => $block->getPlugin()->getPluginDefinition()['admin_label']]);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Create link to full block form.
    $query = [];
    if ($destination = $this->getRequest()->query->get('destination')) {
      $query['destination'] = $destination;
    }
    $form['advanced_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Advanced block options'),
      '#url' => $this->entity->toUrl('edit-form', ['query' => $query]),
      '#weight' => 1000,
    ];

    // Remove the ID and region elements.
    unset($form['id'], $form['region'], $form['settings']['admin_label']);

    // Only show the label input if the label will be shown on the page.
    $form['settings']['label_display']['#weight'] = -100;
    $form['settings']['label']['#states']['visible'] = [
      ':input[name="settings[label_display]"]' => ['checked' => TRUE],
    ];

    $form['settings']['label']['#process'][] = [get_class($this), 'processLabelInput'];
    return $form;
  }

  /**
   * Element process callback for block label element.
   *
   * Checks to make sure the label has a value even if it was set to invisible
   * on the form via javascript.
   */
  public static function processLabelInput(&$element, FormStateInterface &$form_state, array &$form) {
    $input = $form_state->getUserInput();
    if (isset($input['settings']['label']) && empty($input['settings']['label_display'])) {
      $element['#value'] = $element['#default_value'];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save @block', ['@block' => $this->entity->getPlugin()->getPluginDefinition()['admin_label']]);
    $actions['delete']['#access'] = FALSE;
    return $actions;
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
  protected function getPluginForm(BlockPluginInterface $block) {
    if ($block instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($block, 'off_canvas', 'configure');
    }
    return $block;
  }

}

<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to add a block.
 *
 * @internal
 */
class AddBlockForm extends ConfigureBlockFormBase {

  use AjaxFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_add_block';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitLabel() {
    return $this->t('Add Block');
  }

  /**
   * Builds the form for the block.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string|null $plugin_id
   *   The plugin ID of the block to add.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $plugin_id = NULL) {
    // Only generate a new component once per form submission.
    if (!$component = $form_state->getTemporaryValue('layout_builder__component')) {
      $component = new SectionComponent($this->uuidGenerator->generate(), $region, ['id' => $plugin_id]);
      $section_storage->getSection($delta)->appendComponent($component);
      $form_state->setTemporaryValue('layout_builder__component', $component);
    }
    $form = $this->doBuildForm($form, $form_state, $section_storage, $delta, $component);

    // static::ajaxSubmit() requires data-drupal-selector to be the same between
    // the various Ajax requests. A bug in \Drupal\Core\Form\FormBuilder
    // prevents that from happening unless $form['#id'] is also the same.
    // Normally, #id is set to a unique HTML ID via Html::getUniqueId(), but
    // here we bypass that in order to work around the data-drupal-selector bug.
    // This is okay so long as we assume that this form only ever occurs once on
    // a page.
    // @todo Remove this workaround once https://www.drupal.org/node/2897377 is
    //   fixed.
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    return $form;
  }

}

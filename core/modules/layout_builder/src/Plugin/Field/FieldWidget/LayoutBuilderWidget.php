<?php

namespace Drupal\layout_builder\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\TranslatableSectionStorageInterface;

/**
 * A widget to display the layout form.
 *
 * @FieldWidget(
 *   id = "layout_builder_widget",
 *   label = @Translation("Layout Builder Widget"),
 *   description = @Translation("A field widget for Layout Builder."),
 *   field_types = {
 *     "layout_section",
 *     "layout_translation"
 *   },
 *   multiple_values = TRUE,
 * )
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
class LayoutBuilderWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#type' => 'layout_builder',
      '#section_storage' => $this->getSectionStorage($form_state),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // @todo This isn't resilient to being set twice, during validation and
    //   save https://www.drupal.org/project/drupal/issues/2833682.
    if (!$form_state->isValidationComplete()) {
      return;
    }
    $field_name = $this->fieldDefinition->getName();
    $section_storage = $this->getSectionStorage($form_state);
    if ($field_name === OverridesSectionStorage::FIELD_NAME) {
      $items->setValue($section_storage->getSections());
    }
    elseif ($field_name === OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME && $section_storage instanceof TranslatableSectionStorageInterface) {
      $items->set(0, $section_storage->getTranslatedConfiguration());
    }
    else {
      throw new \LogicException("Widget used with unexpected field, $field_name for section storage: " . $section_storage->getStorageType());
    }
  }

  /**
   * Gets the section storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage loaded from the tempstore.
   */
  private function getSectionStorage(FormStateInterface $form_state) {
    return $form_state->getFormObject()->getSectionStorage();
  }

}

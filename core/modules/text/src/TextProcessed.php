<?php

namespace Drupal\text;

use Drupal\Core\Render\Markup;
use Drupal\filter\FilterProcessResult;

/**
 * A computed property for processing text with a format.
 *
 * Required settings (below the definition's 'settings' key) are:
 *  - text source: The text property containing the to be processed text.
 */
class TextProcessed extends TextProcessedResult {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $value = parent::getValue();

    if ($value !== '' || ($value instanceof FilterProcessResult && $value->getProcessedText() !== '')) {
      $value = Markup::create((string) $value);
    }
    return $value;
  }

}

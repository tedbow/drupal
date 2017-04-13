<?php

namespace Drupal\text;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\filter\FilterProcessResult;

/**
 * A computed property for processing text with a format.
 *
 * Required settings (below the definition's 'settings' key) are:
 *  - text source: The text property containing the to be processed text.
 *
 * @deprecated in Drupal 8.3.x and will be removed before Drupal 9.0.0.
 *  This functionality will be moved into \Drupal\text\TextProcessed
 */
class TextProcessedResult extends TypedData {

  /**
   * Cached processed text.
   *
   * @var \Drupal\filter\FilterProcessResult|string|null
   */
  protected $processed = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);

    if ($definition->getSetting('text source') === NULL) {
      throw new \InvalidArgumentException("The definition's 'text source' key has to specify the name of the text property to be processed.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->processed !== NULL) {
      return $this->processed;
    }

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->getParent();
    $text = $item->{($this->definition->getSetting('text source'))};

    // Avoid running check_markup() on empty strings.
    if (!isset($text) || $text === '') {
      $this->processed = '';
    }
    else {
      $build = [
        '#type' => 'processed_text',
        '#text' => $text,
        '#format' => $item->format,
        '#filter_types_to_skip' => [],
        '#langcode' => $item->getLangcode(),
      ];
      // It's necessary to capture the cacheability metadata associated with the
      // processed text. See https://www.drupal.org/node/2278483.
      $processed_text = $this->getRenderer()->renderPlain($build);
      $this->processed = FilterProcessResult::createFromRenderArray($build)->setProcessedText((string) $processed_text);
    }
    return $this->processed;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->processed = $value;
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * Returns the renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   */
  protected function getRenderer() {
    return \Drupal::service('renderer');
  }

}

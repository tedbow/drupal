<?php

namespace Drupal\hal\Normalizer;

use Drupal\Core\Render\RendererInterface;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;

/**
 * Adds processed text from text fields to normalizer data.
 *
 * This class does not implement DenormalizerInterface because the 'processed'
 * attribute is computed and represents text processed by the filter system
 * therefore it cannot be provided when creating new entities.
 */
class TextItemBaseNormalizer extends FieldItemNormalizer {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = TextItemBase::class;

  /**
   * Constructs a TextItemBaseNormalizer object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = array()) {
    /** @var \Drupal\text\Plugin\Field\FieldType\TextItemBase $field_item */
    $values = parent::normalize($field_item, $format, $context);

    $field = $field_item->getParent();

    $processed_text = $field_item->process_result;
    if (!empty($context['cacheability'])) {
      /** @var \Drupal\Core\Cache\CacheableMetadata $cacheability */
      $cacheability = $context['cacheability'];
      $cacheability->addCacheableDependency($processed_text);
    }
    $values[$field->getName()][0]['processed'] = $this->serializer->normalize($processed_text->getProcessedText(), $format, $context);
    return $values;
  }

}

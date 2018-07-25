<?php

namespace Drupal\layout_builder\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;

/**
 * Defines a Section Storage type annotation object.
 *
 * @see \Drupal\layout_builder\SectionStorage\SectionStorageManager
 * @see plugin_api
 *
 * @Annotation
 */
class SectionStorage extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * {@inheritdoc}
   */
  public function get() {
    $definition = new SectionStorageDefinition($this->definition);
    $definition->addContextDefinition('entity', new EntityContextDefinition());
    return $definition;
  }

}

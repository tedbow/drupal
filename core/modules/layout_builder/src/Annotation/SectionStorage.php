<?php

namespace Drupal\layout_builder\Annotation;

use Drupal\Component\Annotation\Plugin;
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
   * The plugin weight, optional (defaults to 0).
   *
   * When an entity with layout is rendered, section storage plugins are
   * checked, in order of their weight, to determine which one should be used
   * to render the layout.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * Any required context definitions, optional.
   *
   * When an entity with layout is rendered, all section storage plugins which
   * match a particular set of contexts are checked, in order of their weight,
   * to determine which plugin should be used to render the layout.
   *
   * @var array
   *
   * @see \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface::findByContext()
   */
  public $context = [];

  /**
   * {@inheritdoc}
   */
  public function get() {
    return new SectionStorageDefinition($this->definition);
  }

}

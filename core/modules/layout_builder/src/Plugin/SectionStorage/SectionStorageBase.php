<?php

namespace Drupal\layout_builder\Plugin\SectionStorage;

use Drupal\Core\Plugin\ContextAwarePluginBase;
use Drupal\layout_builder\Routing\LayoutBuilderRoutesTrait;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a base class for Section Storage types.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
abstract class SectionStorageBase extends ContextAwarePluginBase implements SectionStorageInterface {

  use LayoutBuilderRoutesTrait;

  /**
   * Gets the section list.
   *
   * @return \Drupal\layout_builder\SectionListInterface
   *   The section list.
   */
  abstract protected function getSectionList();

  /**
   * {@inheritdoc}
   */
  public function getStorageType() {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return $this->getSectionList()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    return $this->getSectionList()->getSections();
  }

  /**
   * {@inheritdoc}
   */
  public function getSection($delta) {
    return $this->getSectionList()->getSection($delta);
  }

  /**
   * {@inheritdoc}
   */
  public function appendSection(Section $section) {
    $this->getSectionList()->appendSection($section);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function insertSection($delta, Section $section) {
    $this->getSectionList()->insertSection($delta, $section);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeSection($delta) {
    $this->getSectionList()->removeSection($delta);
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove after https://www.drupal.org/project/drupal/issues/2982626.
   */
  public function getContextDefinition($name) {
    return $this->getPluginDefinition()->getContextDefinition($name);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove after https://www.drupal.org/project/drupal/issues/2982626.
   */
  public function getContextDefinitions() {
    return $this->getPluginDefinition()->getContextDefinitions();
  }

}

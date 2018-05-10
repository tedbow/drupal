<?php


namespace Drupal\layout_builder\Event;


use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Prepare Layout sections for the UI.
 *
 * @see \Drupal\layout_builder\LayoutBuilderEvents::PREPARE_SECTIONS_FOR_UI
 */
class PrepareLayoutForUiEvent extends Event {


  /**
   * The original sections.
   *
   * @var \Drupal\layout_builder\Section[]
   */
  protected $originalSections;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * Whether the Layout is rebuilding.
   *
   * @var bool
   */
  protected $isRebuilding;

  /**
   * PrepareLayoutForUiEvent constructor.
   *
   * @param \Drupal\layout_builder\Section[] $originalSections
   * @param \Drupal\layout_builder\SectionStorageInterface $sectionStorage
   * @param bool $isRebuilding
   */
  public function __construct(array $originalSections, SectionStorageInterface $sectionStorage, $isRebuilding) {
    $this->originalSections = $originalSections;
    $this->sectionStorage = $sectionStorage;
    $this->isRebuilding = $isRebuilding;
  }

  /**
   * Determines whether the layout is rebuilding.
   *
   * @return bool
   *   TRUE if the layout is rebuilding, otherwise FALSE.
   */
  public function isRebuilding() {
    return $this->isRebuilding;
  }

  /**
   * Gets the section storage.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage.
   */
  public function getSectionStorage() {
    return $this->sectionStorage;
  }

  /**
   * Gets the original sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The original sections.
   */
  public function getOriginalSections() {
    return $this->originalSections;
  }

}

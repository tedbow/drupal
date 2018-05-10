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
  protected $sections;

  /**
   * Whether the Layout is rebuilding.
   *
   * @var bool
   */
  protected $isRebuilding;

  /**
   * PrepareLayoutForUiEvent constructor.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   * @param bool $isRebuilding
   */
  public function __construct(array $sections, $isRebuilding) {
    $this->sections = $sections;
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
   * Gets the original sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The original sections.
   */
  public function getSections() {
    return $this->sections;
  }

}

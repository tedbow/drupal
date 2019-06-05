<?php

namespace Drupal\layout_builder\ConfigTranslation;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LayoutEntityDisplayUpdater implements ContainerInjectionInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * LayoutEntityDisplayUpdater constructor.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    // The overrides can only be update if the language manager is configurable.
    if ($language_manager instanceof ConfigurableLanguageManagerInterface) {
      $this->languageManager = $language_manager;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
  }

  public function onUpdate(EntityViewDisplayInterface $display) {
    if (empty($this->languageManager)) {
      return;
    }
    if ($display instanceof LayoutEntityDisplayInterface) {
      if ($display->isLayoutBuilderEnabled() && $display->original->isLayoutBuilderEnabled()) {
        $moved_uuids = $this->componentsInNewSections($display);
        foreach ($this->languageManager->getLanguages() as $language) {

        }
      }
    }
  }

  private function componentsInNewSections(LayoutEntityDisplayInterface $display) {
    $moved_uuids = [];
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $original_display */
    $original_display = $display->original;
    $original_sections = $original_display->getSections();
    /// fix to loop through sections.
    $all_original_uuids = array_keys($original_display->getComponents());
    foreach ($display->getSections() as $delta => $section) {
      $original_section_uuids = isset($original_sections[$delta]) ? array_keys($original_sections[$delta]->getComponents()) : [];
      foreach (array_keys($section->getComponents()) as $uuid) {
        if (!in_array($uuid, $original_section_uuids) && in_array($uuid, $all_original_uuids)) {
          $moved_uuids[] = $uuid;
        }
      }

    }
    return $moved_uuids;

  }
}

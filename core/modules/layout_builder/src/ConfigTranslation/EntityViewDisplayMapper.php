<?php

namespace Drupal\layout_builder\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\Event\ConfigMapperPopulateEvent;
use Drupal\config_translation\Event\ConfigTranslationEvents;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;

class EntityViewDisplayMapper extends ConfigEntityMapper {

  use LayoutEntityHelperTrait;

  /**
   * @inheritDoc
   */
  public function populateFromRouteMatch(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    $definition = $this->getPluginDefinition();
    $entity_type = $definition['target_entity_type'];
    $bundle = $definition['target_bundle'];
    $view_mode = $definition['view_mode'];
    $entity = $this->entityTypeManager->getStorage('entity_view_display')->load($entity_type . '.' . $bundle . '.' . $view_mode);
    $this->setEntity($entity);

    $this->langcode = $route_match->getParameter('langcode');

    $event = new ConfigMapperPopulateEvent($this, $route_match);
    $this->eventDispatcher->dispatch(ConfigTranslationEvents::POPULATE_MAPPER, $event);

  }

  /**
   * @inheritDoc
   */
  public function hasTranslatable() {
    $section_storage = $this->getSectionStorageForEntity($this->entity);
    foreach ($section_storage->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        if ($component->hasTranslatableConfiguration()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function hasSchema() {
    return TRUE;
  }


}

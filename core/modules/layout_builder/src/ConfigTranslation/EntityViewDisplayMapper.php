<?php

namespace Drupal\layout_builder\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\Event\ConfigMapperPopulateEvent;
use Drupal\config_translation\Event\ConfigTranslationEvents;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\Form\DefaultsTranslationForm;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use \Symfony\Component\Routing\Route;

class EntityViewDisplayMapper extends ConfigEntityMapper {

  use LayoutEntityHelperTrait;

  /**
   * @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function populateFromRouteMatch(RouteMatchInterface $route_match) {
    $view_mode = $route_match->getParameter('view_mode_name');
    $definition = $this->getPluginDefinition();
    $target_entity_type = $this->entityTypeManager->getDefinition($definition['target_entity_type']);
    $bundle_type = $target_entity_type->getBundleEntityType();
    $entity_type = $definition['target_entity_type'];
    $bundle = $route_match->getParameter($bundle_type);
    $entity = $this->entityTypeManager->getStorage('entity_view_display')->load($entity_type . '.' . $bundle . '.' . $view_mode);
    $this->setEntity($entity);

    $this->langcode = $route_match->getParameter('langcode');

    $event = new ConfigMapperPopulateEvent($this, $route_match);
    $this->eventDispatcher->dispatch(ConfigTranslationEvents::POPULATE_MAPPER, $event);

  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function hasSchema() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters() {
    $target_entity_type = $this->entityTypeManager->getDefinition($this->entity->getTargetEntityTypeId());
    $bundle_type = $target_entity_type->getBundleEntityType();
    $parameters[$bundle_type] = $this->entity->getTargetBundle();
    $parameters['view_mode_name'] = $this->entity->getMode();
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddRoute() {
    $route = parent::getAddRoute();
    $this->modifyAddEditRoutes($route);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditRoute() {
    $route = parent::getEditRoute();
    $this->modifyAddEditRoutes($route);
    return $route;
  }

  /**
   * Modifies to add and edit routes to use DefaultsTranslationForm.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to modify;
   */
  protected function modifyAddEditRoutes(Route $route) {
    $definition = $this->getPluginDefinition();
    $target_entity_type = $this->entityTypeManager->getDefinition($definition['target_entity_type']);
    $bundle_type = $target_entity_type->getBundleEntityType();
    $route->setDefault('bundle_key', $bundle_type);
    $route->setDefault('entity_type_id', $definition['target_entity_type']);
    $route->setDefault('_form', DefaultsTranslationForm::class);
    $route->setDefault('section_storage_type', 'defaults');
    $route->setDefault('section_storage', '');
    $route->setOption('_layout_builder', TRUE);
    $route->setOption('_admin_route', FALSE);
    $route->setOption('parameters', [
      'section_storage' => ['layout_builder_tempstore' => TRUE],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return parent::getTitle() . ': ' . $this->entity->getMode();
  }

}

<?php

namespace Drupal\layout_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines an interface for Section Storage type plugins.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
interface SectionStorageInterface extends SectionListInterface, PluginInspectionInterface, ContextAwarePluginInterface, AccessibleInterface {

  /**
   * Returns an identifier for this storage.
   *
   * @return string
   *   The unique identifier for this storage.
   */
  public function getStorageId();

  /**
   * Returns the type of this storage.
   *
   * Used in conjunction with the storage ID.
   *
   * @return string
   *   The type of storage.
   */
  public function getStorageType();

  /**
   * Provides the routes needed for Layout Builder UI.
   *
   * Allows the plugin to add or alter routes during the route building process.
   * \Drupal\layout_builder\Routing\LayoutBuilderRoutesTrait is provided for the
   * typical use case of building a standard Layout Builder UI.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection.
   *
   * @see \Drupal\Core\Routing\RoutingEvents::ALTER
   */
  public function buildRoutes(RouteCollection $collection);

  /**
   * Derives the required plugin contexts from route values.
   *
   * @param mixed $value
   *   The raw value.
   * @param mixed $definition
   *   The parameter definition provided in the route options.
   * @param string $name
   *   The name of the parameter.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The required plugin contexts.
   */
  public function getContextsFromRoute($value, $definition, $name, array $defaults);

  /**
   * Gets the URL used when redirecting away from the Layout Builder UI.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public function getRedirectUrl();

  /**
   * Gets the URL used to display the Layout Builder UI.
   *
   * @param string $rel
   *   (optional) The link relationship type, for example: 'view' or 'disable'.
   *   Defaults to 'view'.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public function getLayoutBuilderUrl($rel = 'view');

  /**
   * Gets the label for the object using the sections.
   *
   * @return string
   *   The label, or NULL if there is no label defined.
   */
  public function label();

  /**
   * Saves the sections.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function save();

}

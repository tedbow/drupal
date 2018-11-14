<?php

namespace Drupal\layout_builder\SectionStorage;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;

/**
 * Provides the interface for a plugin manager of section storage types.
 *
 * Note that this interface purposefully does not implement
 * \Drupal\Component\Plugin\PluginManagerInterface, as the below methods exist
 * to serve the use case of \Drupal\Component\Plugin\Factory\FactoryInterface
 * and \Drupal\Component\Plugin\Mapper\MapperInterface.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
interface SectionStorageManagerInterface extends DiscoveryInterface {

  /**
   * Loads a section storage with the provided contexts applied.
   *
   * @param string $type
   *   The section storage type.
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   (optional) The contexts available for this storage to use.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage or NULL if its context requirements are not met.
   */
  public function load($type, array $contexts = []);

  /**
   * Finds the section storage to load based on available contexts.
   *
   * @param string $operation
   *   The access operation. See \Drupal\Core\Access\AccessibleInterface.
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   The contexts which should be used to determine which storage to return.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage if one matched all contexts, or NULL otherwise.
   */
  public function findByContext($operation, array $contexts);

  /**
   * Loads an empty section storage with no associated section list.
   *
   * @param string $type
   *   The type of section storage being instantiated.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage.
   *
   * @internal
   *   Section storage relies on context to load section lists. Use ::load()
   *   when that context is available. This method is intended for use by
   *   collaborators of the plugins in build-time situations when section
   *   storage type must be consulted.
   */
  public function loadEmpty($type);

}

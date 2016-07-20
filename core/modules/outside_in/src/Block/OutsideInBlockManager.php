<?php

namespace Drupal\outside_in\Block;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\outside_in\OperationAwareFormInterface;

/**
 * Provides form discovery capabilities for block plugins.
 */
class OutsideInBlockManager implements OutsideInBlockManagerInterface {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * OutsideInBlockManager constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   */
  public function __construct(ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject(PluginInspectionInterface $plugin, $operation) {
    $definition = $plugin->getPluginDefinition();
    if (!isset($definition['form'][$operation])) {
      throw new InvalidPluginDefinitionException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a "%s" form class', $plugin->getPluginId(), $operation));
    }

    // If the form specified is the plugin itself, use it directly.
    if (get_class($plugin) === $definition['form'][$operation]) {
      $form_object = $plugin;
    }
    else {
      $form_object = $this->classResolver->getInstanceFromDefinition($definition['form'][$operation]);
    }

    // Ensure the resulting object is a plugin form.
    if (!$form_object instanceof PluginFormInterface) {
      throw new InvalidPluginDefinitionException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a valid "%s" form class, must implement \Drupal\Core\Plugin\PluginFormInterface', $plugin->getPluginId(), $operation));
    }

    if ($form_object instanceof OperationAwareFormInterface) {
      $form_object->setOperation($operation);
    }

    return $form_object;
  }

}

<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\OperationAwareFormInterface;

/**
 * Provides form discovery capabilities for plugins.
 */
class PluginFormManager implements PluginFormManagerInterface {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * PluginFormManager constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   */
  public function __construct(ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject(PluginInspectionInterface $plugin, $operation, $fallback_operation = NULL) {
    $definition = $plugin->getPluginDefinition();

    if (!isset($definition['form'][$operation])) {
      // Use the default form class if no form is specified for this operation.
      if ($fallback_operation && isset($definition['form'][$fallback_operation])) {
        $operation = $fallback_operation;
      }
      else {
        throw new InvalidPluginDefinitionException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a "%s" form class', $plugin->getPluginId(), $operation));
      }
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

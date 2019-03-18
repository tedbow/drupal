<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates the plugin label if set in the plugin configuration.
 */
class ComponentPluginLabelTranslate implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 200];
    return $events;
  }

  /**
   * Translates the plugin label if set.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $plugin = $event->getPlugin();
    $contexts = $event->getContexts();
    $component = $event->getComponent();
    if (!$plugin instanceof ConfigurableInterface && !isset($contexts['layout_builder.entity'])) {
      return;
    }

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $contexts['layout_builder.entity']->getContextValue();
    if ($entity instanceof FieldableEntityInterface && $entity instanceof TranslatableInterface && !$entity->isDefaultTranslation() && $entity->hasField(OverridesSectionStorage::TRANSLATED_LABELS_FIELD_NAME)) {
      $configuration = $plugin->getConfiguration();
      if (!$entity->get(OverridesSectionStorage::TRANSLATED_LABELS_FIELD_NAME)->isEmpty()) {
        $translated_layout_configuration = $entity->get(OverridesSectionStorage::TRANSLATED_LABELS_FIELD_NAME)->get(0)->getValue();
        if (isset($translated_layout_configuration['value']['components'][$component->getUuid()])) {
          $translated_plugin_configuration = $translated_layout_configuration['value']['components'][$component->getUuid()];
          $translated_plugin_configuration += $configuration;
          $plugin->setConfiguration($translated_plugin_configuration);
        }

      }

    }

  }

}

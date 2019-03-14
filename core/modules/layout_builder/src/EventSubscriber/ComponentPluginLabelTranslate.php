<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
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
    if (!$plugin instanceof ConfigurableInterface && !isset($contexts['layout_builder.entity'])) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $contexts['layout_builder.entity']->getContextValue();
    $langcode = $entity->language()->getId();
    $configuration = $plugin->getConfiguration();
    if (isset($configuration['label']) && isset($configuration['layout_builder_translations'][$langcode]['label'])) {
      $configuration['label'] = $configuration['layout_builder_translations'][$langcode]['label'];
      $plugin->setConfiguration($configuration);
    }
  }

}

<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\TranslatableSectionStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates the plugin configuration if needed.
 */
class ComponentPluginTranslate implements EventSubscriberInterface {

  use LayoutEntityHelperTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 200];
    return $events;
  }

  /**
   * Translates the plugin configuration if needed.
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

    $entity = $contexts['layout_builder.entity']->getContextValue();
    $configuration = $plugin->getConfiguration();
    $section_storage = $this->getSectionStorageForEntity($entity);
    if ($section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation()) {
      if ($translated_plugin_configuration = $section_storage->getTranslatedComponentConfiguration($component->getUuid())) {
        $translated_plugin_configuration += $configuration;
        $plugin->setConfiguration($translated_plugin_configuration);
      }
    }
  }

}

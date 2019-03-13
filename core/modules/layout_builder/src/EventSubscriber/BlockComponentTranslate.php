<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BlockComponentTranslate implements EventSubscriberInterface {

  use LayoutEntityHelperTrait;
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 200];
    return $events;
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();
    if (!$block instanceof BlockPluginInterface) {
      return;

    }
    $contexts = $event->getContexts();
    $entity = $contexts['layout_builder.entity'];
  }

}

<?php

namespace Drupal\layout_builder\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Page title event subscriber.
 */
class PageTitleSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new PageTitleSubscriber instance.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 0];
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

    // Add placeholder for page_title_block in layout_builder admin.
    if ($block->getPluginId() === 'page_title_block' && $this->routeMatch->getRouteObject()->getOption('_layout_builder')) {
      $build = $event->getBuild();
      // Change title to a placeholder.
      $build['content']['#title'] = new TranslatableMarkup('Placeholder for the "@admin_label" block', ['@admin_label' => $block->getPluginDefinition()['admin_label']]);
      $event->setBuild($build);
    }
  }

}

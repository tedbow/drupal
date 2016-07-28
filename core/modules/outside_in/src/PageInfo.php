<?php

namespace Drupal\outside_in;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Outside In Page Info service.
 */
class PageInfo {

  /**
   * The admin theme negotiator.
   *
   * @var \Drupal\Core\Theme\ThemeNegotiatorInterface
   */
  protected $adminThemeNegotiator;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * PageInfo constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Theme\ThemeNegotiatorInterface $admin_theme_negotiator
   *   The admin theme negotiator.
   */
  public function __construct(RouteMatchInterface $route_match, ThemeNegotiatorInterface $admin_theme_negotiator) {
    $this->adminThemeNegotiator = $admin_theme_negotiator;
    $this->routeMatch = $route_match;
  }

  /**
   * Determines whether outside should be use in the current requests.
   *
   * @return bool
   *   True if Outside In should be applied to current request.
   */
  public function useOutsideIn() {
    return !$this->adminThemeNegotiator->applies($this->routeMatch);
  }

}

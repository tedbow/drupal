<?php

namespace Drupal\Core\Access;

use Drupal\Core\Routing\Access\AccessInterface as RoutingAccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Allows access to routes to be controlled by a '_csrf_token' parameter.
 *
 * To use this check you can either provide the token via the URL or the http
 *  header.
 *
 * To use this check via the URL, add a "token" GET parameter to URLs of which
 * the value is a token generated by \Drupal::csrfToken()->get() using the same
 * value as the "_csrf_token" parameter in the route.
 *
 * To use this token via the header, provide the token the 'X-CSRF-Token' http
 * header.
 */
class CsrfAccessCheck implements RoutingAccessInterface {

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * Constructs a CsrfAccessCheck object.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   */
  public function __construct(CsrfTokenGenerator $csrf_token, SessionConfigurationInterface $session_configuration) {
    $this->csrfToken = $csrf_token;
    $this->sessionConfiguration = $session_configuration;
  }

  /**
   * Checks access based on a CSRF token for the request.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, Request $request, RouteMatchInterface $route_match, AccountInterface $account) {
    $path_access = $this->pathAccess($route, $request, $route_match);
    if ($path_access->isNeutral()) {
      $header_access = $this->headerAccess($request, $account);
      if ($header_access->isAllowed()) {
        return $header_access;
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * Checks for a valid csrf token via the path.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function pathAccess(Route $route, Request $request, RouteMatchInterface $route_match) {
    if ($token = $request->query->get('token')) {
      $parameters = $route_match->getRawParameters();
      $path = ltrim($route->getPath(), '/');
      // Replace the path parameters with values from the parameters array.
      foreach ($parameters as $param => $value) {
        $path = str_replace("{{$param}}", $value, $path);
      }
      if ($this->csrfToken->validate($request->query->get('token'), $path)) {
        return AccessResult::allowed();
      }
      else {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::neutral();
  }

  /**
   * Checks access based on token provide in 'X-CSRF-Token' header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function headerAccess(Request $request, AccountInterface $account) {
    $method = $request->getMethod();

    // This check only applies if
    // 1. this is a write operation
    // 2. the user was successfully authenticated and
    // 3. the request comes with a session cookie.
    if (!in_array($method, array('GET', 'HEAD', 'OPTIONS', 'TRACE'))
      && $account->isAuthenticated()
      && $this->sessionConfiguration->hasSession($request)
    ) {
      if ($request->headers->has('X-CSRF-Token')) {
        $csrf_token = $request->headers->get('X-CSRF-Token');
        if ($this->csrfToken->validate($csrf_token, 'rest')) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
    }
    // If no token provided or not a write method then do not allow or forbid.
    return AccessResult::neutral();
  }

}

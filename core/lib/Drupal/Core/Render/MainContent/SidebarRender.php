<?php

namespace Drupal\Core\Render\MainContent;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenSidebarDialogCommand;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default main content renderer for sidebar dialog requests.
 */
class SidebarRender extends DialogRenderer {

  /**
   * {@inheritdoc}
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match) {
    $response = new AjaxResponse();

    // First render the main content, because it might provide a title.
    $content = drupal_render_root($main_content);

    // Attach the library necessary for using the OpenModalDialogCommand and set
    // the attachments for this Ajax response.
    $main_content['#attached']['library'][] = 'core/drupal.sidebar';
    $response->setAttachments($main_content['#attached']);

    // If the main content doesn't provide a title, use the title resolver.
    $title = isset($main_content['#title']) ? $main_content['#title'] : $this->titleResolver->getTitle($request, $route_match->getRouteObject());

    // Determine the title: use the title provided by the main content if any,
    // otherwise get it from the routing information.
    $options = $request->request->get('dialogOptions', array());

    $response->addCommand(new OpenSidebarDialogCommand($title, $content, $options));
    return $response;
  }
}

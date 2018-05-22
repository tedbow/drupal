<?php

/**
 * @file
 * Contains inline_block.page.inc.
 *
 * Page callback for Inline block entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Inline block templates.
 *
 * Default template: inline_block.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_inline_block(array &$variables) {
  // Fetch InlineBlock Entity Object.
  $inline_block = $variables['elements']['#inline_block'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

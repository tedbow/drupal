<?php

namespace Drupal\Core;

use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\Routing\LinkGeneratorTrait;

/**
 * Defines an object that holds information about a link.
 */
class Link implements RenderableInterface {

  /**
   * @deprecated in Drupal 8.0.x-dev, will be removed before Drupal 9.0.0.
   */
  use LinkGeneratorTrait;

  /**
   * The text of the link.
   *
   * @var string
   */
  protected $text;

  /**
   * The URL of the link.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * The link attributes.
   *
   * @var array
   */
  protected $attributes = [];

  /**
   * The link attributes that are needed for dialog use.
   *
   * @var array
   */
  protected $dialogAttributes;

  /**
   * Returns the link attributes.
   *
   * @return array
   *   The link attributes.
   */
  public function getAttributes() {
    // @todo Should we not merge dialogAttributes and attributes here?
    //   Merging gives that attributes that will actually be used.
    $attributes = $this->attributes;
    if ($this->dialogAttributes) {
      if (!isset($attributes['class']) || !in_array('use-ajax', $attributes['class'])) {
        $attributes['class'][] = 'use-ajax';
      }
      $attributes = array_merge($attributes, $this->dialogAttributes);
    }
    return $attributes;
  }

  /**
   * Sets the link attributes.
   *
   * @param array $attributes
   *   The link attributes.
   */
  public function setAttributes(array $attributes) {
    $this->attributes = $attributes;
  }

  /**
   * Constructs a new Link object.
   *
   * @param string $text
   *   The text of the link.
   * @param \Drupal\Core\Url $url
   *   The url object.
   */
  public function __construct($text, Url $url) {
    $this->text = $text;
    $this->url = $url;
  }

  /**
   * Creates a Link object from a given route name and parameters.
   *
   * @param string $text
   *   The text of the link.
   * @param string $route_name
   *   The name of the route
   * @param array $route_parameters
   *   (optional) An associative array of parameter names and values.
   * @param array $options
   *   The options parameter takes exactly the same structure.
   *   See \Drupal\Core\Url::fromUri() for details.
   *
   * @return static
   */
  public static function createFromRoute($text, $route_name, $route_parameters = [], $options = []) {
    return new static($text, new Url($route_name, $route_parameters, $options));
  }

  /**
   * Creates a Link object from a given Url object.
   *
   * @param string $text
   *   The text of the link.
   * @param \Drupal\Core\Url $url
   *   The Url to create the link for.
   *
   * @return static
   */
  public static function fromTextAndUrl($text, Url $url) {
    return new static($text, $url);
  }

  /**
   * Returns the text of the link.
   *
   * @return string
   */
  public function getText() {
    return $this->text;
  }

  /**
   * Sets the new text of the link.
   *
   * @param string $text
   *   The new text.
   *
   * @return $this
   */
  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  /**
   * Returns the URL of the link.
   *
   * @return \Drupal\Core\Url
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Sets the URL of this link.
   *
   * @param Url $url
   *   The URL object to set
   *
   * @return $this
   */
  public function setUrl(Url $url) {
    $this->url = $url;
    return $this;
  }

  /**
   * Generates the HTML for this Link object.
   *
   * Do not use this method to render a link in an HTML context. In an HTML
   * context, self::toRenderable() should be used so that render cache
   * information is maintained. However, there might be use cases such as tests
   * and non-HTML contexts where calling this method directly makes sense.
   *
   * @return \Drupal\Core\GeneratedLink
   *   The link HTML markup.
   *
   * @see \Drupal\Core\Link::toRenderable()
   */
  public function toString() {
    // @todo Ensure this method takes into account attributes.
    return $this->getLinkGenerator()->generateFromLink($this);
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    $renderable = [
      '#type' => 'link',
      '#url' => $this->url,
      '#title' => $this->text,
      '#attributes' => $this->getAttributes(),
    ];
    if ($this->dialogAttributes) {
      $renderable['#attached'] = [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ];
    }
    return $renderable;
  }

  /**
   * Changes the link to open in a dialog.
   *
   * @param string $type
   *   The dialog type 'modal' or 'dialog', defaults to 'modal'.
   * @param string $renderer
   *   The dialog renderer. Core provides the 'off_canvas' renderer which uses
   *   the off-canvas dialog. Other modules can provide dialog renderers by
   *   defining a service that is tagged with the name
   *   'render.main_content_renderer' and tagged with a format in the pattern of
   *   'drupal_[dialogType].[dialogRenderer]'.
   * @param array $options
   *   The dialog options.
   *
   * @return $this
   */
  public function openInDialog($type = 'modal', $renderer = NULL, array $options = []) {
    if (!in_array($type, ['dialog', 'modal'])) {
      throw new \UnexpectedValueException("The dialog type must be either 'dialog' or 'modal'");
    }
    // @todo Do we actually need to check this?
    $main_content_renders = \Drupal::getContainer()->getParameter('main_content_renderers');
    $renderer_key = "drupal_$type" . ($renderer ? ".$renderer" : '');
    if (!isset($main_content_renders[$renderer_key])) {
      throw new \UnexpectedValueException("The renderer '$renderer_key' is not available.");
    }
    $this->dialogAttributes['data-dialog-type'] = $type;
    if ($renderer) {
      $this->dialogAttributes['data-dialog-renderer'] = $renderer;
    }
    if ($options) {
      $this->dialogAttributes['data-dialog-options'] = json_encode($options);
    }
    return $this;
  }

}

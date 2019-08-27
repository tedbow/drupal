<?php

namespace Drupal\FunctionalJavascriptTests;

/**
 * Functions for simulating layout changes.
 */
trait SortableTestTrait {

  /**
   * Callback for ajax update.
   *
   * @param string $item
   *   The HTML selector for the element to be moved.
   * @param string $from
   *   The HTML selector for the previous container element.
   * @param null|string $to
   *   The HTML selector for the target container.
   */
  abstract protected function sortUpdate($item, $from, $to = NULL);

  /**
   * Simulates a drag on an element from one container to another.
   *
   * @param string $item
   *   The HTML selector for the element to be moved.
   * @param string $from
   *   The HTML selector for the previous container element.
   * @param null|string $to
   *   The HTML selector for the target container.
   */
  protected function sortTo($item, $from, $to) {

    $item = addslashes($item);
    $from = addslashes($from);
    $to   = addslashes($to);

    $script = <<<JS
(function (src, to) {
  var sourceElement = document.querySelector(src);
  var toElement = document.querySelector(to);

  toElement.insertBefore(sourceElement, toElement.firstChild);
})('{$item}', '{$to}')

JS;

    $options = [
      'script' => $script,
      'args'   => [],
    ];

    $this->getSession()->getDriver()->getWebDriverSession()->execute($options);
    $this->sortUpdate($item, $from, $to);
  }

  /**
   * Simulates a drag moving an element after its sibling in the same container.
   *
   * @param string $item
   *   The HTML selector for the element to be moved.
   * @param string $target
   *   The HTML selector for the sibling element.
   * @param string $from
   *   The HTML selector for the element container.
   */
  protected function sortAfter($item, $target, $from, $updateFunction = '\Drupal\Tests\layout_builder\FunctionalJavascript\ContentPreviewToggleTest::sortUpdate') {

    $item   = addslashes($item);
    $target = addslashes($target);
    $from   = addslashes($from);

    $script = <<<JS
(function (src, to) {
  var sourceElement = document.querySelector(src);
  var toElement = document.querySelector(to);

  toElement.insertAdjacentElement('afterend', sourceElement);
})('{$item}', '{$target}')

JS;

    $options = [
      'script' => $script,
      'args'   => [],
    ];

    $this->getSession()->getDriver()->getWebDriverSession()->execute($options);
    $this->sortUpdate($item, $from);
  }

}

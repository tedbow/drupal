<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

/**
 * Trait for Layout Builder Javascript tests.
 */
trait LayoutBuilderTestTrait {

  /**
   * Clicks a category in the block list.
   *
   * @param string $category
   *   The category.
   */
  protected function clickBlockCategory($category) {
    $this->assertSession()->elementExists('css', "#drupal-off-canvas summary:contains('$category')")->click();
  }

  /**
   * Asserts that block link is visible.
   *
   * @param string $link_text
   *   The link text.
   */
  protected function assertBlockLinkVisible($link_text) {
    $this->assertTrue($this->getSession()->getPage()->find('css', "#drupal-off-canvas a:contains('$link_text')")->isVisible());
  }

  /**
   * Asserts that block link is not visible.
   *
   * @param string $link_text
   *   The link text.
   */
  protected function assertBlockLinkNotVisible($link_text) {
    $this->assertFalse($this->getSession()->getPage()->find('css', "#drupal-off-canvas a:contains('$link_text')")->isVisible());
  }

}

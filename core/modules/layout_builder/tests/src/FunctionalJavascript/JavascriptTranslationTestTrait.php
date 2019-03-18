<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

/**
 * Common functions for test Layout Builder with translations.
 */
trait JavascriptTranslationTestTrait {

  /**
   * Updates a block label translation.
   *
   * @param string $block_selector
   *   The CSS selector for the block.
   * @param string $expected_label
   *   The label that is expected.
   * @param string $new_label
   *   The new label to set.
   * @param array $unexpected_element_selectors
   *   A list of selectors for elements that should be present.
   */
  protected function updateBlockTranslation($block_selector, $expected_label, $new_label, array $unexpected_element_selectors = []) {
    /** @var \Drupal\Tests\WebAssert $assert_session */
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink($block_selector, 'Translate block');
    $label_input = $assert_session->waitForElementVisible('css', '#drupal-off-canvas [name="settings[label]"]');
    $this->assertNotEmpty($label_input);
    $this->assertEquals($expected_label, $label_input->getValue());
    $label_input->setValue($new_label);
    foreach ($unexpected_element_selectors as $unexpected_element_selector) {
      $assert_session->elementNotExists('css', $unexpected_element_selector);
    }
    $page->pressButton('Translate');
    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', "h2:contains(\"$new_label\")"));
  }

}

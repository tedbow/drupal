<?php

namespace Drupal\Tests\layout_builder\Functional;


/**
 * Common functions for testing Layout Builder with translations.
 */
trait TranslationTestTrait {

  /**
   * @param \Drupal\Tests\WebAssert $assert_session
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertNonTranslationActionsRemoved() {
    $assert_session = $this->assertSession();
    // Confirm that links do not exist to change the layout.
    $assert_session->linkNotExists('Add Section');
    $assert_session->linkNotExists('Add Block');
    $assert_session->linkNotExists('Remove section');
  }

}

<?php

namespace Drupal\Tests\settings_tray\FunctionalJavascript;

use Drupal\block\Entity\Block;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\system\FunctionalJavascript\OffCanvasTestBase;

/**
 * Base class for Settings Tray tests.
 */
class SettingsTrayTestBase extends OffCanvasTestBase {

  use ContextualLinkClickTrait;

  const TOOLBAR_EDIT_LINK_SELECTOR = '#toolbar-bar div.contextual-toolbar-tab button';

  const LABEL_INPUT_SELECTOR = 'input[data-drupal-selector="edit-settings-label"]';

  /**
   * Open block form by clicking the element found with a css selector.
   *
   * @param string $block_selector
   *   A css selector selects the block or an element within it.
   * @param string $contextual_link_container
   *   The element that contains the contextual links. If none provide the
   *   $block_selector will be used.
   */
  protected function openBlockForm($block_selector, $contextual_link_container = '') {
    if (!$contextual_link_container) {
      $contextual_link_container = $block_selector;
    }
    // Ensure that contextual link element is present because this is required
    // to open the off-canvas dialog in edit mode.
    $contextual_link = $this->assertSession()->waitForElement('css', "$contextual_link_container .contextual-links a");
    $this->assertNotEmpty($contextual_link);
    // When page first loads Edit Mode is not triggered until first contextual
    // link is added.
    $this->assertElementVisibleAfterWait('css', '.dialog-off-canvas-main-canvas.js-settings-tray-edit-mode');
    // Ensure that all other Ajax activity is completed.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->click($block_selector);
    $this->waitForOffCanvasToOpen();
    $this->assertOffCanvasBlockFormIsValid();
  }


  /**
   * Enables edit mode by pressing edit button in the toolbar.
   */
  protected function enableEditMode() {
    $this->pressToolbarEditButton();
    $this->assertEditModeEnabled();
  }

  /**
   * Disables edit mode by pressing edit button in the toolbar.
   */
  protected function disableEditMode() {
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->pressToolbarEditButton();
    $this->assertEditModeDisabled();
  }

  /**
   * Press the toolbar Edit button provided by the contextual module.
   */
  protected function pressToolbarEditButton() {
    $this->assertSession()->waitForElement('css', '[data-contextual-id] .contextual-links a');
    $edit_button = $this->getSession()
      ->getPage()
      ->find('css', static::TOOLBAR_EDIT_LINK_SELECTOR);
    $edit_button->press();
  }

  /**
   * Assert that edit mode has been properly disabled.
   */
  protected function assertEditModeDisabled() {
    $web_assert = $this->assertSession();
    // Contextual triggers should be hidden.
    $web_assert->elementExists('css', '.contextual .trigger.visually-hidden');
    // No contextual triggers should be not hidden.
    $web_assert->elementNotExists('css', '.contextual .trigger:not(.visually-hidden)');
    // The toolbar edit button should read "Edit".
    $web_assert->elementContains('css', static::TOOLBAR_EDIT_LINK_SELECTOR, 'Edit');
    // The main canvas element should NOT have the "js-settings-tray-edit-mode"
    // class.
    $web_assert->elementNotExists('css', '.dialog-off-canvas-main-canvas.js-settings-tray-edit-mode');
  }


  /**
   * Assert that edit mode has been properly enabled.
   */
  protected function assertEditModeEnabled() {
    $web_assert = $this->assertSession();
    // No contextual triggers should be hidden.
    $web_assert->elementNotExists('css', '.contextual .trigger.visually-hidden');
    // The toolbar edit button should read "Editing".
    $web_assert->elementContains('css', static::TOOLBAR_EDIT_LINK_SELECTOR, 'Editing');
    // The main canvas element should have the "js-settings-tray-edit-mode" class.
    $web_assert->elementExists('css', '.dialog-off-canvas-main-canvas.js-settings-tray-edit-mode');
  }

  /**
   * Asserts that Off-Canvas block form is valid.
   */
  protected function assertOffCanvasBlockFormIsValid() {
    $web_assert = $this->assertSession();
    // Confirm that Block title display label has been changed.
    $web_assert->elementTextContains('css', '.form-item-settings-label-display label', 'Display block title');
    // Confirm Block title label is shown if checkbox is checked.
    if ($this->getSession()->getPage()->find('css', 'input[name="settings[label_display]"]')->isChecked()) {
      $this->assertEquals($this->isLabelInputVisible(), TRUE, 'Label is visible');
      $web_assert->elementTextContains('css', '.form-item-settings-label label', 'Block title');
    }
    else {
      $this->assertEquals($this->isLabelInputVisible(), FALSE, 'Label is not visible');
    }

    // Check that common block form elements exist.
    $web_assert->elementExists('css', static::LABEL_INPUT_SELECTOR);
    $web_assert->elementExists('css', 'input[data-drupal-selector="edit-settings-label-display"]');
    // Check that advanced block form elements do not exist.
    $web_assert->elementNotExists('css', 'input[data-drupal-selector="edit-visibility-request-path-pages"]');
    $web_assert->elementNotExists('css', 'select[data-drupal-selector="edit-region"]');
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestThemes() {
    // Remove 'seven' theme. Setting Tray "Edit Mode" will not work with 'seven'
    // because it removes all contextual links the off-canvas dialog should.
    return array_filter(parent::getTestThemes(), function ($theme) {
      return $theme !== 'seven';
    });
  }

  /**
   * Gets the block CSS selector.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   *
   * @return string
   *   The CSS selector.
   */
  public function getBlockSelector(Block $block) {
    return '#block-' . str_replace('_', '-', $block->id());
  }

  /**
   * Determines if the label input is visible.
   *
   * @return bool
   *   TRUE if the label is visible, FALSE if it is not.
   */
  protected function isLabelInputVisible() {
    return $this->getSession()->getPage()->find('css', static::LABEL_INPUT_SELECTOR)->isVisible();
  }

}

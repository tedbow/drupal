<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Url;
use Drupal\Tests\layout_builder\Functional\TranslationTestTrait;

/**
 * Tests that inline blocks works with content translation.
 *
 * @group layout_builder
 */
class InlineBlockTranslationTest extends InlineBlockTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use JavascriptTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Adds a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the node type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'bundle_with_section_field', TRUE);
  }

  /**
   * Tests that inline blocks works with content translation.
   */
  public function testInlineBlockContentTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
    ]));

    // Allow layout overrides.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[allow_custom]' => TRUE],
      'Save'
    );

    // Add a new inline block to the original node.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block en label', 'Block en body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en label');
    $assert_session->pageTextContains('Block en body');

    // Create a translation.
    $add_translation_url = Url::fromRoute("entity.node.content_translation_add", [
      'node' => 1,
      'source' => 'en',
      'target' => 'it',
    ]);
    $this->drupalPostForm($add_translation_url, [
      'title[0][value]' => 'The translated node title',
      'body[0][value]' => 'The translated node body',
    ], 'Save');

    // Update the translate node's inline block.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();

    $this->updateBlockTranslation(
      static::INLINE_BLOCK_LOCATOR,
      'Block en label',
      'Block it label',
      ['[name="settings[block_form][body][0][value]"]']
    );

    $this->assertSaveLayout();

    // Enable translation for block_content type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('block_content', 'basic', TRUE);

    // Update the translate node's inline block.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();

    $this->clickContextualLink(static::INLINE_BLOCK_LOCATOR, 'Translate block');
    $textarea = $assert_session->waitForElement('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertEquals('Block en body', $textarea->getValue());
    $textarea->setValue('Block it body');

    $label_input = $assert_session->elementExists('css', '#drupal-off-canvas [name="settings[translated_label]"]');
    $this->assertNotEmpty($label_input);
    $this->assertEquals('Block it label', $label_input->getValue());
    $label_input->setValue('Block Updated it label');
    $page->pressButton('Translate');

    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextContains('Block it body');
    $assert_session->pageTextContains('Block Updated it label');
    $assert_session->pageTextNotContains('Block en body');
    $assert_session->pageTextNotContains('Block en label');
    $this->assertSaveLayout();

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block it body');
    $assert_session->pageTextContains('Block Updated it label');
    $assert_session->pageTextNotContains('Block en body');
    $assert_session->pageTextNotContains('Block en label');

    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block it body');
    $assert_session->pageTextNotContains('Block Updated it label');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextContains('Block en label');
  }

}

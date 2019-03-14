<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Url;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\layout_builder\Functional\TranslationTestTrait;

/**
 * Tests that block settings can be translated.
 *
 * @group layout_builder
 */
class TranslationTest extends WebDriverTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use ContextualLinkClickTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_translation',
    'layout_builder',
    'block',
    'node',
    'contextual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'bundle_with_section_field', 'new_revision' => TRUE]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);
    // Adds a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the node type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'bundle_with_section_field', TRUE);
  }

  /**
   * Tests that block labels can be translated.
   */
  public function testLabelTranslation() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

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

    $this->clickContextualLink('.block-field-blocknodebundle-with-section-fieldbody', 'Configure');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $page->fillField('settings[label]', 'field label untranslated');
    $page->checkField('settings[label_display]');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $this->addBlock('Powered by Drupal', '.block-system-powered-by-block', TRUE, 'untranslated label');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->addressEquals('node/1');

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

    // Update the translations block label.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'untranslated label', 'label in translation');
    // @todo this will fail until https://www.drupal.org/node/3039185
    // $this->updateBlockTranslation('.block-field-blocknodebundle-with-section-fieldbody', 'field label untranslated', 'field label translated');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('label in translation');
    // @todo this will fail until https://www.drupal.org/node/3039185
    // $assert_session->pageTextContains('field label translated');

    // Confirm that untranslated label is still used on default translation.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');
    // @todo this will fail until https://www.drupal.org/node/3039185
    // $assert_session->pageTextContains('field label translated');
    // $assert_session->pageTextNotContains('field label untranslated');
  }

  /**
   * Updates a block label translation.
   *
   * @param string $block_selector
   *   The CSS selector for the block.
   * @param string $expected_label
   *   The label that is expected.
   * @param string $new_label
   *   The new label to set.
   */
  protected function updateBlockTranslation($block_selector, $expected_label, $new_label) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink($block_selector, 'Translate block');
    $label_input = $assert_session->waitForElementVisible('css', '#drupal-off-canvas [name="settings[translated_label]"]');
    $this->assertNotEmpty($label_input);
    $this->assertEquals($expected_label, $label_input->getValue());
    $label_input->setValue($new_label);
    $page->pressButton('Translate');
    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', "h2:contains(\"$new_label\")"));
  }

}

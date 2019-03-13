<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Url;

/**
 * Tests that inline blocks works with content translation.
 *
 * @group layout_builder
 */
class InlineBlockTranslationTest extends InlineBlockTestBase {

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
    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::service('router.builder')->rebuild();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    $this->rebuildContainer();
  }

  /**
   * Tests that inline blocks works with content translation.
   */
  public function testInlineBlockContentTranslation() {
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
    $this->addInlineBlockToLayout('Block en', 'Block en body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en');
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
    $this->configureInlineBlock('Block en body', 'Block it body');
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextNotContains('Block it body');

    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('Block it body');
    $assert_session->pageTextNotContains('Block en body');

    // Update the original node's inline block.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('Block en body', 'Block en body updated');
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en body updated');
    $assert_session->pageTextNotContains('Block it body');

    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('Block it body');
    $assert_session->pageTextNotContains('Block en body updated');
  }

  /**
   * Tests that an translated entity can override the layout from default.
   */
  public function testInlineBlockContentTranslationOverrideFromDefault() {
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

    // Add an inline block to the default layout.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Override the translated node's layout.
    $this->drupalGet('it/node/1/layout');
    $this->configureInlineBlock('The DEFAULT block body', 'Overridden block body');
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('Overridden block body');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('Overridden block body');
    $assert_session->pageTextNotContains('The DEFAULT block body');
  }

  /**
   * Tests deleting an translated entity with inline block.
   */
  public function testDeletingTranslatedEntityWithInlineBlock() {
    /** @var \Drupal\Core\Cron $cron */
    $cron = \Drupal::service('cron');
    /** @var \Drupal\layout_builder\InlineBlockUsage $usage */
    $usage = \Drupal::service('inline_block.usage');

    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
      'delete content translations',
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

    // Override the translated node's layout.
    $this->drupalGet('it/node/1/layout');
    $this->addInlineBlockToLayout('Block it title', 'Block it body');
    $this->assertSaveLayout();
    $it_block = $this->getLatestBlockEntityId();
    $this->assertCount(1, $this->blockStorage->loadMultiple());

    // Add an inline block to the original node.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block en title', 'Block en body');
    $this->assertSaveLayout();
    $this->assertCount(2, $this->blockStorage->loadMultiple());

    // Remove the translation.
    $delete_translation_url = Url::fromRoute('entity.node.content_translation_delete', [
      'node' => 1,
      'language' => 'it',
    ]);
    $this->drupalGet($delete_translation_url);
    $this->drupalPostForm(NULL, [], 'Delete Italian translation');

    $cron->run();

    $this->blockStorage->resetCache([$it_block]);
    $this->assertEmpty($this->blockStorage->load($it_block));
    $this->assertCount(1, $this->blockStorage->loadMultiple());
    $this->assertEmpty($usage->getUsage($it_block));

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en body');
  }

}

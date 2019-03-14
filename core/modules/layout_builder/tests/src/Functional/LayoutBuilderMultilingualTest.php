<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Layout Builder functionality with multiple languages installed.
 *
 * @group layout_builder
 */
class LayoutBuilderMultilingualTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'node',
    'block_content',
    'language',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // There must be more than one block type available to trigger
    // \Drupal\layout_builder\Controller\ChooseBlockController::inlineBlockList().
    BlockContentType::create([
      'id' => 'first_type',
      'label' => 'First type',
    ])->save();
    BlockContentType::create([
      'id' => 'second_type',
      'label' => 'Second type',
    ])->save();

    // Create a translatable content type with layout overrides enabled.
    $this->createContentType([
      'type' => 'bundle_with_section_field',
    ]);

    LayoutBuilderEntityViewDisplay::load('node.bundle_with_section_field.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    // Create a second language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Create a node and translate it.
    $en_node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The untranslated node title',
    ]);
    $en_node->save();

    // Create a second node in Spanish to confirm the UI works in another
    // language. We cannot use a translation for this because layout
    // translations do not have the full UI.
    $es_node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The untranslated node title',
      'langcode' => 'es',
    ]);
    $es_node->save();

    $this->drupalLogin($this->createUser([
      'configure any layout',
      'translate interface',
    ]));
  }

  /**
   * Tests that custom blocks are available for translated entities.
   */
  public function testCustomBlocks() {
    // Check translated and untranslated entities before translating the string.
    $this->assertCustomBlocks('node/1');
    $this->assertCustomBlocks('es/node/2');

    // Translate the 'Inline blocks' string used as a category in
    // \Drupal\layout_builder\Controller\ChooseBlockController::inlineBlockList().
    $this->drupalPostForm('admin/config/regional/translate', ['string' => 'Inline blocks'], 'Filter');
    $this->drupalPostForm(NULL, ['Translated string (Spanish)' => 'Bloques en linea'], 'Save translations');

    // Check translated and untranslated entities after translating the string.
    $this->assertCustomBlocks('node/1');
    $this->assertCustomBlocks('es/node/2');
  }

  /**
   * Asserts that custom blocks are available.
   *
   * @param string $url
   *   The URL for a Layout Builder enabled entity.
   */
  protected function assertCustomBlocks($url) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet($url);
    $page->clickLink('Layout');
    $page->clickLink('Add Block');
    $page->clickLink('Create custom block');
    $assert_session->linkExists('First type');
    $assert_session->linkExists('Second type');
  }

}

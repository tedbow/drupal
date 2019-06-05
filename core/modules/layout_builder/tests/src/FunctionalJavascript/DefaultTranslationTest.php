<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\layout_builder\Functional\TranslationTestTrait;

/**
 * Tests that default layouts can be translated.
 *
 * @group layout_builder
 */
class DefaultTranslationTest extends WebDriverTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use JavascriptTranslationTestTrait;
  use ContextualLinkClickTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'config_translation',
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

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
      'translate configuration',
    ]));

    // Allow layout overrides.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
  }

  /**
   * Dataprovider for testDefaultTranslation().
   */
  public function providerDefaultTranslation() {
    return [
      'has translated node' => [TRUE],
      'no translated node' => [FALSE],
    ];
  }

  /**
   * Tests default translations.
   *
   * @dataProvider providerDefaultTranslation
   */
  public function testDefaultTranslation($translate_node = FALSE) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $expected_it_body = 'The node body';
    if ($translate_node) {
      // Create a translation.
      $add_translation_url = Url::fromRoute("entity.node.content_translation_add", [
        'node' => 1,
        'source' => 'en',
        'target' => 'it',
      ]);
      $expected_it_body = 'The translated node body';
      $this->drupalPostForm($add_translation_url, [
        'title[0][value]' => 'The translated node title',
        'body[0][value]' => $expected_it_body,
      ], 'Save');

    }
    $manage_display_url = static::FIELD_UI_PREFIX . '/display/default';
    $this->drupalGet($manage_display_url);
    $assert_session->linkExists('Manage layout');

    $this->drupalGet("$manage_display_url/translate");
    // Assert that translation is not available when there are no settings to
    // translate.
    $assert_session->pageTextContains('You are not authorized to access this page.');

    $this->drupalGet($manage_display_url);
    $page->clickLink('Manage layout');

    // Add a block with a translatable setting.
    $this->addBlock('Powered by Drupal', '.block-system-powered-by-block', TRUE, 'untranslated label');
    $page->pressButton('Save layout');
    $assert_session->addressEquals($manage_display_url);

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->pageTextContains('untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->pageTextContains('untranslated label');


    $this->drupalGet("$manage_display_url/translate");
    // Assert that translation is  available when there are settings to
    // translate.
    $assert_session->pageTextNotContains('You are not authorized to access this page.');


    $page->clickLink('Add');
    $assert_session->addressEquals('admin/structure/types/manage/bundle_with_section_field/display/default/translate/it/add');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'untranslated label', 'label in translation');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->pageTextContains('The layout translation has been saved.');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->pageTextContains('untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->pageTextContains('label in translation');

    // Confirm the settings in the 'Add' form were saved and can be updated.
    $this->drupalGet("$manage_display_url/translate");
    $assert_session->linkNotExists('Add');
    $this->getEditLink($page)->click();
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'label in translation', 'label update1 in translation');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->pageTextContains('The layout translation has been saved.');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->pageTextContains('untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->pageTextContains('label update1 in translation');

    // Confirm the settings in 'Edit' where save correctly and can be updated.
    $this->drupalGet("$manage_display_url/translate");
    $assert_session->linkNotExists('Add');
    $this->getEditLink($page)->click();
    $assert_session->addressEquals('admin/structure/types/manage/bundle_with_section_field/display/default/translate/it/edit');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'label update1 in translation', 'label update2 in translation');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->pageTextContains('The layout translation has been saved.');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->pageTextContains('untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->pageTextContains('label update2 in translation');

    // Move block to different section.
    $this->drupalGet($manage_display_url);
    $assert_session->linkExists('Manage layout');
    $page->clickLink('Manage layout');
    $this->clickLink('Add section', 1);
    $this->assertNotEmpty($assert_session->waitForElementVisible('named', ['link', 'Two column']));

    $this->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas [data-drupal-selector="edit-actions-submit"]'));
    $page->pressButton('Add section');
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.layout__region--second'));

    // Drag the block to a region in different section.
    $page->find('css', '.layout__region--content .block-system-powered-by-block')->dragTo($page->find('css', '.layout__region--second'));
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Save layout');
    $assert_session->addressEquals($manage_display_url);

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->elementTextContains('css', '.layout__region--second', 'untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->elementTextContains('css', '.layout__region--second', 'label update2 in translation');

    // Confirm translated configuration can be edited in the new section.
    $this->drupalGet("$manage_display_url/translate");
    $assert_session->linkNotExists('Add');
    $this->getEditLink($page)->click();
    $assert_session->addressEquals('admin/structure/types/manage/bundle_with_section_field/display/default/translate/it/edit');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.layout__region--second .block-system-powered-by-block', 'label update2 in translation', 'label update3 in translation');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->pageTextContains('The layout translation has been saved.');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->elementTextContains('css', '.layout__region--second', 'untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->elementTextContains('css', '.layout__region--second', 'label update3 in translation');

    // Ensure the translation can be deleted.
    $this->drupalGet("$manage_display_url/translate");
    $page->find('css', '.dropbutton-arrow')->click();
    $delete_link = $assert_session->waitForElementVisible('css', 'a:contains("Delete")');
    $this->assertNotEmpty($delete_link);
    $delete_link->click();
    $assert_session->pageTextContains('This action cannot be undone.');
    $page->pressButton('Delete');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The node body');
    $assert_session->pageTextContains('untranslated label');
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains($expected_it_body);
    $assert_session->pageTextContains('untranslated label');

    $this->drupalGet("$manage_display_url/translate");
    $assert_session->linkExists('Add');
  }

  /**
   * Gets the edit link for the default layout translation.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The edit link.
   */
  protected function getEditLink() {
    $page = $this->getSession()->getPage();
    $edit_link_locator = 'a[href$="admin/structure/types/manage/bundle_with_section_field/display/default/translate/it/edit"]';
    $edit_link = $page->find('css', $edit_link_locator);
    $this->assertNotEmpty($edit_link);
    return $edit_link;
  }

}

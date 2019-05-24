<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\layout_builder\Functional\TranslationTestTrait;

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
    'dblog',
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

    // Allow layout overrides.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
  }

  public function testDefaultTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
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

    $this->drupalGet("$manage_display_url/translate");
    // Assert that translation is  available when there are settings to
    // translate.
    $assert_session->pageTextNotContains('You are not authorized to access this page.');

    $page->clickLink('Add');
    $assert_session->addressEquals('admin/structure/types/manage/bundle_with_section_field/display/default/translate/it/add');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'untranslated label', 'label in translation');






  }

}

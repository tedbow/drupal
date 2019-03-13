<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;

/**
 * Tests that the Layout Builder UI works with translated content.
 *
 * @group layout_builder
 */
class LayoutBuilderTranslationTest extends ContentTranslationTestBase {

  use TranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_translation',
    'contextual',
    'entity_test',
    'layout_builder',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpViewDisplay();
    $this->setUpEntities();
  }

  /**
   * Tests that the Layout Builder UI works with translated content.
   */
  public function testLayoutPerTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $entity_url = $this->entity->toUrl('canonical')->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();
    $layout_url = $entity_url . '/layout';
    $translated_layout_url = $translated_entity_url . '/layout';

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    // If there is not a layout override the layout translation is not
    // accessible.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');

    // Ensure that the tempstore varies per-translation.
    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    // Adjust the layout of the original entity.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $page->pressButton('Add Block');

    $assert_session->pageTextContains('Powered by Drupal');

    // Confirm the tempstore for the translated layout is not affected.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');

    $this->drupalGet($layout_url);
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');
    $assert_session->pageTextContains('Powered by Drupal');

    // Ensure that the layout change propagates to the translated entity.
    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextContains('Powered by Drupal');

    // The translate layout is not available.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextNotContains('Access denied');
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');

    // Confirm that links do not exist to change the layout.
    $assert_session->linkNotExists('Add Section');
    $assert_session->linkNotExists('Add Block');
    $assert_session->linkNotExists('Remove section');
  }


  /**
   * Tests that access is denied to a layout translation if there is override.
   */
  public function testLayoutTranslationNoOverride() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $entity_url = $this->entity->toUrl('canonical')->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();
    $layout_url = $entity_url . '/layout';
    $translated_layout_url = $translated_entity_url . '/layout';

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

    // If there is not a layout override the layout translation is not
    // accessible.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');
  }

}

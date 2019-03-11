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

    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

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
    $assert_session->pageTextNotContains('Powered by Drupal');

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

    // The translated entity's unaltered layout still persists in the tempstore.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');

    $assert_session->addressEquals($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextNotContains('Powered by Drupal');
  }


  /**
   * Tests creating an override on a translation without an existing override.
   */
  public function testLayoutTranslationFromDefault() {
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

    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

    // Adjust the layout of the translated entity.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $page->pressButton('Add Block');

    $assert_session->pageTextContains('Powered by Drupal');

    // Confirm the tempstore for the translated layout is not affected.
    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('Powered by Drupal');

    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');

    // Ensure that the layout is on the translated entity.
    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextContains('Powered by Drupal');

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');
    $assert_session->pageTextNotContains('Powered by Drupal');

    // The untranslated entity's unaltered layout still persists in the
    // tempstore.
    $this->drupalGet($layout_url);
    $assert_session->pageTextContains('The untranslated field value');
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');

    $assert_session->addressEquals($entity_url);
    $assert_session->pageTextContains('The untranslated field value');
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextNotContains('Powered by Drupal');
  }

}

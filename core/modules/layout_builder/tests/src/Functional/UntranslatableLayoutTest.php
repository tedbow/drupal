<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;

/**
 * Tests that the Layout Builder UI works with untranslatable layout field.
 *
 * @group layout_builder
 */
class UntranslatableLayoutTest extends ContentTranslationTestBase {

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

    $field_config = FieldConfig::loadByName($this->entityTypeId, $this->bundle, OverridesSectionStorage::FIELD_NAME);
    $field_config->setTranslatable(FALSE);
    $field_config->save();
  }

  /**
   * Tests that layout translations are not available.
   */
  public function testLayoutTranslationDenied() {
    $assert_session = $this->assertSession();

    $entity_url = $this->entity->toUrl('canonical')->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();
    $layout_url = $entity_url . '/layout';
    $translated_layout_url = $translated_entity_url . '/layout';

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');
    $assert_session->linkExists('Layout');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->linkNotExists('Layout');

    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('Access denied');

    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');
  }

}

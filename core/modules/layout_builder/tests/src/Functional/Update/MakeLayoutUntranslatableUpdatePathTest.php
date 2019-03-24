<?php

namespace Drupal\Tests\layout_builder\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for translatable layouts.
 *
 * @see layout_builder_post_update_make_layout_untranslatable()
 *
 * @group layout_builder
 * @group legacy
 */
class MakeLayoutUntranslatableUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/layout-builder.php',
      __DIR__ . '/../../../fixtures/update/layout-builder-field-schema.php',
      __DIR__ . '/../../../fixtures/update/layout-builder-translation.php',
    ];
  }

  /**
   * Tests the upgrade path for translatable layouts.
   */
  public function testRunUpdates() {
    $this->runUpdates();

    $assert_session = $this->assertSession();
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Test Article - New title');
    $assert_session->pageTextNotContains('Test Article - Spanish title');
    $assert_session->pageTextContains('This is in English');
    $assert_session->pageTextNotContains('This is in Spanish');

    $this->drupalGet('es/node/1');
    $assert_session->pageTextNotContains('Test Article - New title');
    $assert_session->pageTextContains('Test Article - Spanish title');
    $assert_session->pageTextNotContains('This is in English');
    $assert_session->pageTextContains('This is in Spanish');

    // Set up a new content type that is translatable and overridable.
    $this->createContentType(['type' => 'new_content_type']);
    $this->container->get('content_translation.manager')->setEnabled('node', 'new_content_type', TRUE);
    EntityViewDisplay::load('node.new_content_type.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    // Test both an existing and new content type.
    $this->assertTranslatedLayoutWorkflow('article', TRUE);
    $this->assertTranslatedLayoutWorkflow('new_content_type', FALSE);
  }

  /**
   * Tests the workflow for translating a layout.
   *
   * @param string $type
   *   The content type machine name.
   * @param bool $translated_layout_expected
   *   TRUE if the translated layout is expected, FALSE otherwise.
   */
  private function assertTranslatedLayoutWorkflow($type, $translated_layout_expected) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet("admin/structure/types/manage/$type/display");

    $node = $this->createNode(['title' => 'Default language', 'type' => $type]);
    $this->drupalGet($node->toUrl());
    $page->clickLink('Layout');
    $page->pressButton('Save layout');
    $page->clickLink('Translate');
    $page->clickLink('Add');
    $page->fillField('title[0][value]', 'Spanish translation');

    // Do not use strings directly while on the translated paths.
    $page->pressButton('edit-submit');
    $layout_href = str_replace($this->baseUrl, '', $this->getSession()->getCurrentUrl()) . '/layout';
    if ($translated_layout_expected) {
      $assert_session->linkByHrefExists($layout_href);
    }
    else {
      $assert_session->linkByHrefNotExists($layout_href);
    }


    $this->drupalGet($node->toUrl());
    $page->clickLink('Layout');
    $page->clickLink('Add Section');
    $page->clickLink('Four column');
    $page->clickLink('Add Block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'Custom block label on default language');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add Block');
    $page->pressButton('Save layout');
    $assert_session->pageTextContains('Custom block label on default language');
    $this->drupalGet('es' . $node->toUrl()->toString());
    if ($translated_layout_expected) {
      $assert_session->pageTextNotContains('Custom block label on default language');
    }
    else {
      $assert_session->pageTextContains('Custom block label on default language');
    }
  }

}

<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the ability for opting in and out of Layout Builder.
 *
 * @group layout_builder
 */
class LayoutBuilderOptInTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'field_ui',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create one content type before installing Layout Builder and one after.
    $this->createContentType(['type' => 'before']);
    $this->container->get('module_installer')->install(['layout_builder']);
    $this->rebuildAll();
    $this->createContentType(['type' => 'after']);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
    ]));
  }

  /**
   * Tests the expected default values for enabling Layout Builder.
   */
  public function testDefaultValues() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // The content type created before Layout Builder was installed is still
    // using the Field UI.
    $this->drupalGet('admin/structure/types/manage/before/display/default');
    $assert_session->checkboxNotChecked('layout[enable_defaults]');

    // The content type created after Layout Builder was installed is now using
    // the Layout Builder UI.
    $field_ui_prefix = 'admin/structure/types/manage/after/display/default';
    $this->drupalGet($field_ui_prefix);
    $assert_session->checkboxChecked('layout[enable_defaults]');
    $page->pressButton('Save');

    // Find the UUID of the body field within Layout Builder.
    $delta = 0;
    $display = $this->container->get('entity_type.manager')->getStorage('entity_view_display')->load('node.after.default');
    $body_component = NULL;
    foreach ($display->getSection($delta)->getComponents() as $component) {
      if ($component->get('field_name') === 'body') {
        $body_component = $component;
      }
    }
    $this->assertNotNull($body_component);
    $layout_builder_ui = 'layout_builder/update/block/defaults/node.after.default/0/content/' . $body_component->getUuid();

    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');

    // Change the body formatter to Trimmed.
    $this->drupalGet($layout_builder_ui);
    $assert_session->fieldValueEquals('settings[formatter][type]', 'text_default');
    $page->selectFieldOption('settings[formatter][type]', 'text_trimmed');
    $page->pressButton('Update');
    $assert_session->linkExists('Save Layout');
    $this->clickLink('Save Layout');
    $this->htmlOutput($page->getContent());
    $this->drupalGet($layout_builder_ui);
    $assert_session->fieldValueEquals('settings[formatter][type]', 'text_trimmed');

    // Disable Layout Builder.
    $this->drupalPostForm($field_ui_prefix, ['layout[enable_defaults]' => FALSE], 'Save');
    // The Layout Builder UI is no longer accessible.
    $this->drupalGet($layout_builder_ui);
    $assert_session->statusCodeEquals(403);

    // The changed body formatter is reflected in Field UI.
    $this->drupalGet($field_ui_prefix);
    $assert_session->fieldValueEquals('fields[body][type]', 'text_trimmed');

    // Change the body formatter to Summary.
    $page->selectFieldOption('fields[body][type]', 'text_summary_or_trimmed');
    $page->pressButton('Save');
    $assert_session->fieldValueEquals('fields[body][type]', 'text_summary_or_trimmed');

    // Reenable Layout Builder.
    $this->drupalPostForm($field_ui_prefix, ['layout[enable_defaults]' => TRUE], 'Save');
    // The changed body formatter is reflected in Layout Builder UI.
    $this->drupalGet($layout_builder_ui);
    $assert_session->fieldValueEquals('settings[formatter][type]', 'text_summary_or_trimmed');
  }

}

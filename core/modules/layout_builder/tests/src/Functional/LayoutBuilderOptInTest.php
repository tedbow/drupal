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

    // Both the content type created before and after Layout Builder was
    // installed is still using the Field UI.
    $this->drupalGet('admin/structure/types/manage/before/display/default');
    $assert_session->checkboxNotChecked('layout[is_enabled]');

    $field_ui_prefix = 'admin/structure/types/manage/after/display/default';
    $this->drupalGet($field_ui_prefix);
    $assert_session->checkboxNotChecked('layout[is_enabled]');
    $page->checkField('layout[is_enabled]');
    $page->pressButton('Save');

    $layout_builder_ui = $this->getPathForFieldBlock('node', 'after', 'default', 'body');

    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    // Ensure the body appears once and only once.
    $assert_session->elementsCount('css', '.field--name-body', 1);

    // Change the body formatter to Trimmed.
    $this->drupalGet($layout_builder_ui);
    $assert_session->fieldValueEquals('settings[formatter][type]', 'text_default');
    $page->selectFieldOption('settings[formatter][type]', 'text_trimmed');
    $page->pressButton('Update');
    $assert_session->linkExists('Save Layout');
    $this->clickLink('Save Layout');

    $this->drupalGet($layout_builder_ui);
    $assert_session->fieldValueEquals('settings[formatter][type]', 'text_trimmed');

    // Disable Layout Builder.
    $this->drupalPostForm($field_ui_prefix, ['layout[is_enabled]' => FALSE], 'Save');
    $page->pressButton('Confirm');

    // The Layout Builder UI is no longer accessible.
    $this->drupalGet($layout_builder_ui);
    $assert_session->statusCodeEquals(403);

    // The original body formatter is reflected in Field UI.
    $this->drupalGet($field_ui_prefix);
    $assert_session->fieldValueEquals('fields[body][type]', 'text_default');

    // Change the body formatter to Summary.
    $page->selectFieldOption('fields[body][type]', 'text_summary_or_trimmed');
    $page->pressButton('Save');
    $assert_session->fieldValueEquals('fields[body][type]', 'text_summary_or_trimmed');

    // Reactivate Layout Builder.
    $this->drupalPostForm($field_ui_prefix, ['layout[is_enabled]' => TRUE], 'Save');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    // Ensure the body appears once and only once.
    $assert_session->elementsCount('css', '.field--name-body', 1);

    // The changed body formatter is reflected in Layout Builder UI.
    $this->drupalGet($this->getPathForFieldBlock('node', 'after', 'default', 'body'));
    $assert_session->fieldValueEquals('settings[formatter][type]', 'text_summary_or_trimmed');
  }

  /**
   * Returns the path to update a field block in the UI.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $view_mode
   *   The view mode.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The path.
   */
  protected function getPathForFieldBlock($entity_type_id, $bundle, $view_mode, $field_name) {
    $delta = 0;
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display */
    $display = $this->container->get('entity_type.manager')->getStorage('entity_view_display')->load("$entity_type_id.$bundle.$view_mode");
    $body_component = NULL;
    foreach ($display->getSection($delta)->getComponents() as $component) {
      if ($component->getPluginId() === "field_block:$entity_type_id:$bundle:$field_name") {
        $body_component = $component;
      }
    }
    $this->assertNotNull($body_component);
    return 'layout_builder/update/block/defaults/node.after.default/0/content/' . $body_component->getUuid();
  }

}

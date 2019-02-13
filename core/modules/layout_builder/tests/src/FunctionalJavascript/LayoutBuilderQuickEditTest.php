<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\quickedit\FunctionalJavascript\QuickEditIntegrationTest;

/**
 * Tests that Layout Builder functions with Quick Edit.
 *
 * @covers layout_builder_entity_view_alter()
 * @covers layout_builder_quickedit_render_field()
 *
 * @group layout_builder
 */
class LayoutBuilderQuickEditTest extends QuickEditIntegrationTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['layout_builder'];

  /**
   * Whether the test should use an override for the node.
   *
   * @var bool
   */
  protected $useOverride;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $user = $this->loggedInUser;
    $this->loginLayoutAdmin();

    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/types/manage/article/display/default');
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    $this->drupalGet('admin/structure/block/block-content/manage/basic/display');
    $page->checkField('layout[enabled]');
    $page->pressButton('Save');

    $this->drupalLogout();

    $this->drupalLogin($user);
  }

  /**
   * @param bool $useOverride
   *
   * @dataProvider provideTestArticleNode
   */
  public function testArticleNode($useOverride = FALSE) {
    $this->useOverride = $useOverride;
    parent::testArticleNode();
    // @todo Test publised, title or other field that does appear in manage dispaly.
  }

  public function provideTestArticleNode() {
    return [
      'no override' => [FALSE],
      'use override' => [TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function drupalCreateNode(array $settings = []) {
    $node = parent::drupalCreateNode($settings);
    $assert_session = $this->assertSession();
    if ($this->useOverride) {
      $user = $this->loggedInUser;
      $this->loginLayoutAdmin();
      $this->drupalGet('node/' . $node->id() . '/layout');
      $assert_session->buttonExists('Save layout');
      $this->getSession()->getPage()->pressButton('Save layout');
      $this->assertNotEmpty($assert_session->waitForElement('css', '.messages--status'));
      $assert_session->pageTextContains('The layout override has been saved.');
      $this->drupalLogin($user);
    }
    return $node;
  }


  /**
   * {@inheritdoc}
   */
  protected function assertEntityInstanceFieldStates($entity_type_id, $entity_id, $entity_instance_id, array $expected_field_states) {
    $expected_field_states = $this->replaceLayoutBuilderFieldIdKeys($expected_field_states);
    parent::assertEntityInstanceFieldStates($entity_type_id, $entity_id, $entity_instance_id, $expected_field_states);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertEntityInstanceFieldMarkup($entity_type_id, $entity_id, $entity_instance_id, array $expected_field_attributes) {
    $expected_field_attributes = $this->replaceLayoutBuilderFieldIdKeys($expected_field_attributes);
    parent::assertEntityInstanceFieldMarkup($entity_type_id, $entity_id, $entity_instance_id, $expected_field_attributes);
  }

  /**
   * Replaces the keys in the array with field IDs used for Layout Builder.
   *
   * @param array $array
   *   The array with field IDs as keys.
   *
   * @return array
   *   The array with the keys replaced.
   */
  protected function replaceLayoutBuilderFieldIdKeys(array $array) {
    $layout_builder_expected_states = [];
    foreach ($array as $field_key => $value) {
      list($entity_type, $entity_id, $field_name, , $view_mode) = explode('/', $field_key);
      $layout_builder_expected_states[$this->getQuickEditFieldId($entity_type, $entity_id, $field_name, $view_mode)] = $value;
    }
    return $layout_builder_expected_states;
  }

  /**
   * Gets view mode that Layout Builder used for a field.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The view mode used by layout builder.
   */
  protected function getLayoutBuilderViewMode($entity_type, $entity_id, $field_name, $view_mode) {
    if (in_array($field_name, ['title', 'uid', 'created'])) {
      return $view_mode;
    }
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $view_display = EntityViewDisplay::collectRenderDisplay($entity, 'default');
    $sections = $view_display->getSections();
    // Find the body field component.
    foreach (reset($sections)->getComponents() as $component) {
      if ($component->getPlugin()->getPluginId() === "field_block:$entity_type:{$entity->bundle()}:$field_name") {
        return 'layout_builder-0-' . $component->getUuid();
      }
    }
    $this->fail("Component not found for: $entity_type, $entity_id, $field_name");
  }

  /**
   * {@inheritdoc}
   */
  protected function getQuickEditFieldId($entity_type, $entity_id, $field_name, $view_mode) {
    $view_mode = $this->getLayoutBuilderViewMode($entity_type, $entity_id, $field_name, $view_mode);
    return parent::getQuickEditFieldId($entity_type, $entity_id, $field_name, $view_mode);
  }

  /**
   * Login the Layout admin user for the test.
   */
  protected function loginLayoutAdmin() {
    // Enable for the layout builder.
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'access content',
      'administer node display',
      'administer node fields',
      'administer block_content display',
      'administer block_content fields',
      'administer blocks',
    ]));
  }

}

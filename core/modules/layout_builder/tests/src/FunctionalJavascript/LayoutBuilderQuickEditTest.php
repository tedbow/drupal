<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
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
  public static $modules = [
    'layout_builder',
    'layout_builder_test_css_transitions',
  ];

  /**
   * Whether the test is currently using Layout Builder on the entity.
   *
   * @var bool
   */
  protected $usingLayoutBuilder = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Save the current user to re-login after Layout Builder changes.
    $user = $this->loggedInUser;
    $this->loginLayoutAdmin();

    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/block/block-content/manage/basic/display');
    $page->checkField('layout[enabled]');
    $page->pressButton('Save');

    $this->drupalLogin($user);
  }

  /**
   * Tests enabling and displaying Layout Builder on a node.
   *
   * @dataProvider providerEnableDisableLayoutBuilder
   */
  public function testEnableDisableLayoutBuilder($use_revisions) {
    if (!$use_revisions) {
      $content_type = NodeType::load('article');
      $content_type->setNewRevision(FALSE);
      $content_type->save();
    }

    $node = $this->createNodeWithTerm();

    $this->assertQuickEditInit($node);
    $this->enableLayouts('admin/structure/types/manage/article/display/default');
    $this->usingLayoutBuilder = TRUE;
    // Test article with Layout Builder enabled.
    // $this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);
    $this->assertQuickEditInit($node);

    // Test article with Layout Builder override.
    $this->createLayoutOverride('node/' . $node->id() . '/layout');
    $this->assertQuickEditInit($node);

    if (!$use_revisions) {
      // Test article with Layout Builder when reverted back to defaults.
      $this->revertLayoutToDefaults('node/' . $node->id() . '/layout');
      //$this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);
      $this->assertQuickEditInit($node);
      //$this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);

      // Test with Layout Builder disabled after being enabled.
      $this->usingLayoutBuilder = FALSE;
      $this->disableLayoutBuilder('admin/structure/types/manage/article/display/default');
      $this->assertQuickEditInit($node);
    }
  }

  public function providerEnableDisableLayoutBuilder() {
    return [
      'use revisions' => [TRUE],
      'do not use revisions' => [FALSE],
    ];
  }

  /**
   * @param bool $useOverride
   *
   * @dataProvider provideTestArticleNode
   */
  public function testArticleNode($useOverride = FALSE) {
    $this->enableLayouts('admin/structure/types/manage/article/display/default');
    $this->usingLayoutBuilder = TRUE;
    $node = $this->createNodeWithTerm();
    if ($useOverride) {
      $this->createLayoutOverride('node/' . $node->id() . '/layout');
    }
    $this->doTestArticle($node);
  }

  /**
   * {@inheritdoc}
   */
  public function testCustomBlock() {
    $this->usingLayoutBuilder = TRUE;
    parent::testCustomBlock();
  }

  /**
   * @todo.
   */
  protected function enableLayouts($path) {
    // Save the current user to re-login after Layout Builder changes.
    $user = $this->loggedInUser;
    $this->loginLayoutAdmin();
    $page = $this->getSession()->getPage();
    $this->drupalGet($path);
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');
    $this->drupalLogin($user);
  }

  /**
   * Data provider for testArticleNode().
   */
  public function provideTestArticleNode() {
    return [
      'no override' => [FALSE],
      'use override' => [TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function assertEntityInstanceFieldStates($entity_type_id, $entity_id, $entity_instance_id, array $expected_field_states) {
    if ($this->usingLayoutBuilder) {
      $expected_field_states = $this->replaceLayoutBuilderFieldIdKeys($expected_field_states);
    }
    parent::assertEntityInstanceFieldStates($entity_type_id, $entity_id, $entity_instance_id, $expected_field_states);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertEntityInstanceFieldMarkup($entity_type_id, $entity_id, $entity_instance_id, array $expected_field_attributes) {
    if ($this->usingLayoutBuilder) {
      $expected_field_attributes = $this->replaceLayoutBuilderFieldIdKeys($expected_field_attributes);
    }
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
      // Extract from $field_key all of the information we need to call
      // getQuickEditFieldId(). The fourth part of $field_key, language code, is
      // not needed so it can be ignored.
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
   * @param string $view_mode
   *   The view mode.
   *
   * @return string
   *   The view mode used by layout builder.
   */
  protected function getLayoutBuilderViewMode($entity_type, $entity_id, $field_name, $view_mode) {
    // If the field is one that is not rendered by Layout Builder do not change
    // $view_mode.
    if (in_array($field_name, ['title', 'uid', 'created'])) {
      return $view_mode;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $view_display = EntityViewDisplay::collectRenderDisplay($entity, 'default');
    $sections = $view_display->getSections();
    // Find the component with the plugin ID in the field_block format that
    // matches the entity type, bundle, and field name.
    foreach (reset($sections)->getComponents() as $component) {
      $component_in_view_mode = str_replace('-', '_', $component->getUuid());
      if ($component->getPlugin()->getPluginId() === "field_block:$entity_type:{$entity->bundle()}:$field_name") {
        return 'layout_builder-0-' . $component_in_view_mode . '-' . $entity->id();
      }
    }
    $this->fail("Component not found for: $entity_type, $entity_id, $field_name");
  }

  /**
   * {@inheritdoc}
   */
  protected function getQuickEditFieldId($entity_type, $entity_id, $field_name, $view_mode) {
    if ($this->usingLayoutBuilder) {
      $view_mode = $this->getLayoutBuilderViewMode($entity_type, $entity_id, $field_name, $view_mode);
    }
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

  /**
   * @todo.
   *
   * @param string $layout_url
   */
  protected function createLayoutOverride($layout_url) {
    $assert_session = $this->assertSession();
    // Save the current user to re-login after Layout Builder changes.
    $user = $this->loggedInUser;
    $this->loginLayoutAdmin();
    $this->drupalGet($layout_url);
    $assert_session->buttonExists('Save layout');
    $this->getSession()->getPage()->pressButton('Save layout');
    $this->assertNotEmpty($assert_session->waitForElement('css', '.messages--status'));
    $assert_session->pageTextContains('The layout override has been saved.');
    $this->drupalLogin($user);
  }

  /**
   * Revert a layout override.
   *
   * @param string $path
   *   The path for the layout builder.
   */
  protected function revertLayoutToDefaults($path) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    // Save the current user to re-login after Layout Builder changes.
    $user = $this->loggedInUser;
    $this->loginLayoutAdmin();
    $this->drupalGet($path);
    $assert_session->buttonExists('Revert to defaults');
    $page->pressButton('Revert to defaults');
    $page->pressButton('Revert');
    $assert_session->pageTextContains('The layout has been reverted back to defaults.');
    // $this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);
    $this->drupalLogin($user);
  }

  /**
   * Disable layout builder.
   *
   * @param string $path
   *   The path to the manage display page.
   */
  protected function disableLayoutBuilder($path) {
    $page = $this->getSession()->getPage();
    // Save the current user to re-login after Layout Builder changes.
    $user = $this->loggedInUser;
    $this->loginLayoutAdmin();
    $this->drupalGet($path);
    $page->uncheckField('layout[allow_custom]');
    $page->uncheckField('layout[enabled]');
    $page->pressButton('Save');
    // @todo why no confirm here?
    // $this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);
    $page->pressButton('Confirm');
    $this->drupalLogin($user);
  }

  private function assertQuickEditInit(\Drupal\node\NodeInterface $node) {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $node = Node::load($node->id());
    $this->drupalGet('node/' . $node->id());

    // Initial state.
    $this->awaitQuickEditForEntity('node', 1);
    $this->assertEntityInstanceStates([
      'node/1[0]' => 'closed',
    ]);
    $this->assertEntityInstanceFieldStates('node', 1, 0, [
      'node/1/title/en/full'      => 'inactive',
      'node/1/uid/en/full'        => 'inactive',
      'node/1/created/en/full'    => 'inactive',
      'node/1/body/en/full'       => 'inactive',
      'node/1/field_tags/en/full' => 'inactive',
    ]);
  }

}

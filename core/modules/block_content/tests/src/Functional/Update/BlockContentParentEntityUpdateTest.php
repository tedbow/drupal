<?php

namespace Drupal\Tests\block_content\Functional\Update;

use Drupal\block_content\Entity\BlockContent;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests entity parent fields update functions for the Block Content module.
 *
 * @group Update
 */
class BlockContentParentEntityUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests adding parent entity fields to the block content entity type.
   *
   * @see block_content_update_8600
   * @see block_content_post_update_add_views_parent_filter
   */
  public function testParentFieldsAddition() {
    $assert_session = $this->assertSession();
    $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();

    // Delete custom block library view.
    View::load('block_content')->delete();
    // Install the test module with the 'block_content' view with an extra
    // display with overridden filters. This extra display should also have the
    // 'has_parent' filter added so that it does not expose fields with parents
    // This display also a filter  only show blocks that contain 'block2' in the
    // 'info' field.
    $this->container->get('module_installer')->install(['block_content_view_override']);

    // Run updates.
    $this->runUpdates();

    // Check that the 'parent_entity_type' field exists and is configured
    // correctly.
    $parent_type_field = $entity_definition_update_manager->getFieldStorageDefinition('parent_entity_type', 'block_content');
    $this->assertEquals('Parent entity type', $parent_type_field->getLabel());
    $this->assertEquals('The parent entity type.', $parent_type_field->getDescription());
    $this->assertEquals(FALSE, $parent_type_field->isRevisionable());
    $this->assertEquals(FALSE, $parent_type_field->isTranslatable());

    // Check that the 'parent_entity_id' field exists and is configured
    // correctly.
    $parent_id_field = $entity_definition_update_manager->getFieldStorageDefinition('parent_entity_id', 'block_content');
    $this->assertEquals('Parent ID', $parent_id_field->getLabel());
    $this->assertEquals('The parent entity ID.', $parent_id_field->getDescription());
    $this->assertEquals(FALSE, $parent_id_field->isRevisionable());
    $this->assertEquals(FALSE, $parent_id_field->isTranslatable());

    $after_block1 = BlockContent::create([
      'info' => 'After update block1',
      'type' => 'basic_block',
    ]);
    $after_block1->save();
    // Add second block that will be shown with the 'info' filter on the
    // additional view display.
    $after_block2 = BlockContent::create([
      'info' => 'After update block2',
      'type' => 'basic_block',
    ]);
    $after_block2->save();

    $this->assertEquals(FALSE, $after_block1->hasParentEntity());
    $this->assertEquals(FALSE, $after_block2->hasParentEntity());

    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);

    $block_with_parent = BlockContent::create([
      'info' => 'block1 with parent',
      'type' => 'basic_block',
    ]);
    $block_with_parent->setParentEntity($admin_user);
    $block_with_parent->save();
    // Add second block that would be shown with the 'info' filter on the
    // additional view display if the 'has_parent' filter was not added.
    $block2_with_parent = BlockContent::create([
      'info' => 'block2 with parent',
      'type' => 'basic_block',
    ]);
    $block2_with_parent->setParentEntity($admin_user);
    $block2_with_parent->save();
    $this->assertEquals(TRUE, $block_with_parent->hasParentEntity());
    $this->assertEquals(TRUE, $block2_with_parent->hasParentEntity());



    // Ensure the Custom Block view shows the blocks without parents only.
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextContains($after_block1->label());
    $assert_session->pageTextContains($after_block2->label());
    $assert_session->pageTextNotContains($block_with_parent->label());
    $assert_session->pageTextNotContains($block2_with_parent->label());

    // Ensure the view's other display also only shows blocks without parent and
    // still filters on the 'info' field.
    $this->drupalGet('extra-view-display');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextNotContains($after_block1->label());
    $assert_session->pageTextContains($after_block2->label());
    $assert_session->pageTextNotContains($block_with_parent->label());
    $assert_session->pageTextNotContains($block2_with_parent->label());

    // Ensure the Custom Block listing without Views installed shows the only
    // blocks without parents.
    $this->drupalGet('admin/structure/block/block-content');
    $this->container->get('module_installer')->uninstall(['views_ui', 'views']);
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseNotContains('view-id-block_content');
    $assert_session->pageTextContains($after_block1->label());
    $assert_session->pageTextContains($after_block2->label());
    $assert_session->pageTextNotContains($block_with_parent->label());
    $assert_session->pageTextNotContains($block2_with_parent->label());

    $this->drupalGet('block/' . $after_block1->id());
    $assert_session->statusCodeEquals('200');

    // Ensure the user who can access a block parent they can access edit form
    // edit route is not accessible.
    $this->drupalGet('block/' . $block_with_parent->id());
    $assert_session->statusCodeEquals('200');

    $this->drupalLogout();

    $this->drupalLogin($this->createUser([
      'access user profiles',
      'administer blocks',
    ]));
    $this->drupalGet('block/' . $after_block1->id());
    $assert_session->statusCodeEquals('200');

    $this->drupalGet('block/' . $block_with_parent->id());
    $assert_session->statusCodeEquals('403');

    $this->drupalLogin($this->createUser([
      'administer blocks',
    ]));

    $this->drupalGet('block/' . $after_block1->id());
    $assert_session->statusCodeEquals('200');

    $this->drupalGet('user/' . $admin_user->id());
    $assert_session->statusCodeEquals('403');

    $this->drupalGet('block/' . $block_with_parent->id());
    $assert_session->statusCodeEquals('403');

  }

}

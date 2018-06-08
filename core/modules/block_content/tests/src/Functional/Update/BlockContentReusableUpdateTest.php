<?php

namespace Drupal\Tests\block_content\Functional\Update;

use Drupal\block_content\Entity\BlockContent;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests 'reusable' field related update functions for the Block Content module.
 */
class BlockContentReusableUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests adding a reusable field to the block content entity type.
   *
   * @see block_content_update_8600
   * @see block_content_update_8601
   */
  public function testReusableFieldAddition() {
    $assert_session = $this->assertSession();
    $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();

    // Delete custom block library view.
    View::load('block_content')->delete();
    // Install the test module with the 'block_content' view with an extra
    // display with overridden filters. This extra display should also have a
    // filter added for 'reusable' field so that it does not expose non-reusable
    // fields. This display also a filter  only show blocks that contain
    // 'block2' in the 'info' field.
    $this->container->get('module_installer')->install(['block_content_view_override']);

    // Run updates.
    $this->runUpdates();

    // Check that the field exists and is configured correctly.
    $reusable_field = $entity_definition_update_manager->getFieldStorageDefinition('reusable', 'block_content');
    $this->assertEquals('Reusable', $reusable_field->getLabel());
    $this->assertEquals('A boolean indicating whether this block is reusable.', $reusable_field->getDescription());
    $this->assertEquals(FALSE, $reusable_field->isRevisionable());
    $this->assertEquals(FALSE, $reusable_field->isTranslatable());

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

    $this->assertEquals(TRUE, $after_block1->isReusable());
    $this->assertEquals(TRUE, $after_block2->isReusable());

    $non_reusable_block = BlockContent::create([
      'info' => 'non-reusable block1',
      'type' => 'basic_block',
      'reusable' => FALSE,
    ]);
    $non_reusable_block->save();
    // Add second block that will be would shown with the 'info' filter on the
    // additional view display if the 'reusable filter was not added.
    $non_reusable_block2 = BlockContent::create([
      'info' => 'non-reusable block2',
      'type' => 'basic_block',
      'reusable' => FALSE,
    ]);
    $non_reusable_block2->save();
    $this->assertEquals(FALSE, $non_reusable_block->isReusable());
    $this->assertEquals(FALSE, $non_reusable_block2->isReusable());

    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);

    // Ensure the Custom Block view shows the reusable blocks but not
    // the non-reusable block.
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextContains($after_block1->label());
    $assert_session->pageTextContains($after_block2->label());
    $assert_session->pageTextNotContains($non_reusable_block->label());
    $assert_session->pageTextNotContains($non_reusable_block2->label());

    // Ensure the views other display also filters out non-reusable blocks and
    // still filters on the 'info' field.
    $this->drupalGet('extra-view-display');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextNotContains($after_block1->label());
    $assert_session->pageTextContains($after_block2->label());
    $assert_session->pageTextNotContains($non_reusable_block->label());
    $assert_session->pageTextNotContains($non_reusable_block2->label());

    $this->drupalGet('block/' . $after_block1->id());
    $assert_session->statusCodeEquals('200');

    // Ensure that non-reusable blocks edit form edit route is not accessible.
    $this->drupalGet('block/' . $non_reusable_block->id());
    $assert_session->statusCodeEquals('403');

    // Ensure the Custom Block listing without Views installed shows the
    // reusable blocks but not the non-reusable blocks.
    // the non-reusable block.
    $this->drupalGet('admin/structure/block/block-content');
    $this->container->get('module_installer')->uninstall(['views_ui', 'views']);
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseNotContains('view-id-block_content');
    $assert_session->pageTextContains($after_block1->label());
    $assert_session->pageTextContains($after_block2->label());
    $assert_session->pageTextNotContains($non_reusable_block->label());
    $assert_session->pageTextNotContains($non_reusable_block2->label());
  }

}

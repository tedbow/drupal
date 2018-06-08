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
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testReusableFieldAddition() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();

    module_load_install('system');
    system_update_8501();
    module_load_install('block_content');
    block_content_update_8400();

    /** @var \Drupal\block_content\Entity\BlockContent $pre_block_1 */
    $pre_block_1 = BlockContent::create([
      'info' => 'Previous block 1',
      'type' => 'basic_block',
    ]);
    $pre_block_1->save();


    /** @var \Drupal\block_content\Entity\BlockContent $pre_block_2 */
    $pre_block_2 = BlockContent::create([
      'info' => 'Previous block 2',
      'type' => 'basic_block',
    ]);
    $pre_block_2->save();

    // Add another page display.
    $data_table = $this->container->get('entity_type.manager')->getDefinition('block_content')->getDataTable();
    /** @var \Drupal\views\Entity\View $view */
    //$view = $this->config('views.view.block_content');
    $view = View::load('block_content');
    $view_ex = $view->getExecutable();


    $new_display_id = $view->duplicateDisplayAsType('page_1', 'page');
    $view->save();
    // By setting the current display the changed marker will appear on the new
    // display.

    $display = $view->getDisplay($new_display_id);
    $display['display_options']['filters']['info'] = [
      'id' => 'info_1',
      'plugin_id' => 'string',
      'table' => $data_table,
      'field' => "info",
      'value' => 'block 2',
      'operator' => 'contains',
      'entity_type' => "block_content",
      'entity_field' => "info",
    ];
    $display['display_options']['path'] = 'block-content/silly-filter';
    // Save off the base part of the config path we are updating.
    /*     $base = "display.$new_display_id.display_options.filters.info_1";

   $view->set("$base.id", 'info_1')
        ->set("$base.plugin_id", 'string')
        ->set("$base.table", $data_table)
        ->set("$base.field", "info")
        ->set("$base.value", 'block 2')
        ->set("$base.operator", 'contains')
        ->set("$base.entity_type", "block_content")
        ->set("$base.entity_field", "info");
      //$view->set("display.$new_display_id.display_options.path", 'block-content/silly-filter');*/
    $view->save();


    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);

    // Ensure the standard Custom Block view shows the reusable blocks but not
    // the non-reusable block.
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextContains($pre_block_1->label());
    $assert_session->pageTextContains($pre_block_2->label());

    // Ensure the standard Custom Block view shows the reusable blocks but not
    // the non-reusable block.
    $this->drupalGet('block-content/silly-filter');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextNotContains($pre_block_1->label());
    $assert_session->pageTextContains($pre_block_2->label());


    // Run updates.
    $this->runUpdates();

    // Check that the field exists and has the correct label.
    $reusable_field = $entity_definition_update_manager->getFieldStorageDefinition('reusable', 'block_content');
    $this->assertEquals('Reusable', $reusable_field->getLabel());

    $storage = $this->container->get('entity_type.manager')->getStorage('block_content');

    $pre_block_1 = $storage->load($pre_block_1->id());

    $this->assertEquals(TRUE, $pre_block_1->isReusable());

    $after_block = BlockContent::create([
      'info' => 'After update block',
      'type' => 'basic_block',
    ]);
    $after_block->save();

    /** @var \Drupal\block_content\Entity\BlockContent $after_block */
    $after_block = $storage->load($after_block->id());

    $this->assertEquals(TRUE, $after_block->isReusable());

    $non_reusable_block = BlockContent::create([
      'info' => 'non-reusable block',
      'type' => 'basic_block',
      'reusable' => FALSE,
    ]);
    $non_reusable_block->save();

    $this->assertEquals(FALSE, $non_reusable_block->isReusable());

    // Ensure the standard Custom Block view shows the reusable blocks but not
    // the non-reusable block.
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseContains('view-id-block_content');
    $assert_session->pageTextContains($pre_block_1->label());
    $assert_session->pageTextContains($after_block->label());
    $assert_session->pageTextNotContains($non_reusable_block->label());

    // Ensure that reusable blocks edit form edit route is accessible.
    $this->drupalGet('block/' . $pre_block_1->id());
    $assert_session->statusCodeEquals('200');
    $this->drupalGet('block/' . $after_block->id());
    $assert_session->statusCodeEquals('200');

    // Ensure that non-reusable blocks edit form edit route is not accessible.
    $this->drupalGet('block/' . $non_reusable_block->id());
    $assert_session->statusCodeEquals('403');

    // Ensure the Custom Block listing without Views installed shows the
    // reusable blocks but not the non-reusable block.
    // the non-reusable block.
    $this->drupalGet('admin/structure/block/block-content');
    $this->container->get('module_installer')->uninstall(['views_ui', 'views']);
    $this->drupalGet('admin/structure/block/block-content');
    $assert_session->statusCodeEquals('200');
    $assert_session->responseNotContains('view-id-block_content');
    $assert_session->pageTextContains($pre_block_1->label());
    $assert_session->pageTextContains($after_block->label());
    $assert_session->pageTextNotContains($non_reusable_block->label());
  }

}

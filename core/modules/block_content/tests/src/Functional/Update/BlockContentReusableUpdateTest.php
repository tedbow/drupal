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

/*    require_once $this->root . '/core/includes/install.inc';
    require_once $this->root . '/core/includes/update.inc';

    drupal_load_updates();
    update_fix_compatibility();

    $context = [
      'sandbox' => [
        '#finished' => 1,
      ],
    ];
    update_do_one('system', 8501, [], $context);
    print_r($context);
    $context = [
      'sandbox' => [
        '#finished' => 1,
      ],
    ];
    update_do_one('block_content', 8400, [], $context);
    print_r($context);*/

    /** @var \Drupal\block_content\Entity\BlockContent $pre_block_1 */
    $pre_block_1 = BlockContent::create([
      'info' => 'Previous block1',
      'type' => 'basic_block',
    ]);
    $pre_block_1->save();


    /** @var \Drupal\block_content\Entity\BlockContent $pre_block_2 */
    $pre_block_2 = BlockContent::create([
      'info' => 'Previous block2',
      'type' => 'basic_block',
    ]);
    $pre_block_2->save();

    // Delete custom block library view.
    View::load('block_content')->delete();
    // Install the test module with the 'block_content' view with another
    // display with overridden filters.
    $this->container->get('module_installer')->install(['block_content_view_override']);


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
    $this->drupalGet('blocks-override');
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

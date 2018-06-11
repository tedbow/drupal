<?php

namespace Drupal\Tests\block_content\Functional\Wizard;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests node wizard and generic entity integration.
 *
 * @group Views
 * @group node
 */
class BlockContentWizardTest extends ViewTestBase {

  public static $modules = ['block_content', 'views_ui'];

  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->drupalLogin($this->drupalCreateUser(['administer views']));
    // Create the basic bundle since it is provided by standard.
    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
      'revision' => FALSE,
    ]);
  }

  /**
   * Tests creating a view with node titles.
   */
  public function testViewAddBlockContent() {
    // Create the basic bundle since it is provided by standard.
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
      'revision' => FALSE,
    ]);
    $bundle->save();

    $view = [];
    $view['label'] = $this->randomMachineName(16);
    $view['id'] = strtolower($this->randomMachineName(16));
    $view['description'] = $this->randomMachineName(16);
    $view['page[create]'] = FALSE;
    $view['show[wizard_key]'] = 'block_content';
    $this->drupalPostForm('admin/structure/views/add', $view, t('Save and edit'));

    $view_storage_controller = \Drupal::entityManager()->getStorage('view');
    /** @var \Drupal\views\Entity\View $view */
    $view = $view_storage_controller->load($view['id']);

    $display_options = $view->getDisplay('default')['display_options'];

    $this->assertEquals('block_content', $display_options['filters']['reusable']['entity_type']);
    $this->assertEquals('reusable', $display_options['filters']['reusable']['entity_field']);
    $this->assertEquals('reusable', $display_options['filters']['reusable']['type']);

  }

}

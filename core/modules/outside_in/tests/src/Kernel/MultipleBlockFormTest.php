<?php

namespace Drupal\Tests\outside_in\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\offcanvas_test\Form\OffCanvasForm;

/**
 * Tests that blocks can have multiple forms.
 *
 * @group outside_in
 */
class MultipleBlockFormTest extends KernelTestBase {

  /**
   * @var array
   */
  public static $modules = ['system', 'outside_in', 'offcanvas_test'];

  /**
   * Tests that blocks can have multiple forms.
   */
  public function testMultipleForms() {
    $block = \Drupal::service('plugin.manager.block')->createInstance('offcanvas_links_block');

    $form_object1 = \Drupal::service('outside_in.block.manager')->getFormObject($block, 'default');
    $form_object2 = \Drupal::service('outside_in.block.manager')->getFormObject($block, 'offcanvas');

    // Assert that the block itself is used for the default form.
    $this->assertSame($block, $form_object1);

    $expected_offcanvas = new OffCanvasForm();
    $expected_offcanvas->setOperation('offcanvas');
    $this->assertEquals($expected_offcanvas, $form_object2);
  }

}

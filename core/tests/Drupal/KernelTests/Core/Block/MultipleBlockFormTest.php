<?php

namespace Drupal\KernelTests\Core\Block;

use Drupal\block_test\Form\SecondaryBlockForm;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that blocks can have multiple forms.
 *
 * @group block
 */
class MultipleBlockFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'block', 'block_test'];

  /**
   * Tests that blocks can have multiple forms.
   */
  public function testMultipleForms() {
    $block = \Drupal::service('plugin.manager.block')->createInstance('test_multiple_forms_block');

    $form_object1 = \Drupal::service('plugin_form.manager')->getFormObject($block, 'default');
    $form_object2 = \Drupal::service('plugin_form.manager')->getFormObject($block, 'secondary');

    // Assert that the block itself is used for the default form.
    $this->assertSame($block, $form_object1);

    $expected_secondary = new SecondaryBlockForm();
    $expected_secondary->setOperation('secondary');
    $this->assertEquals($expected_secondary, $form_object2);
  }

}

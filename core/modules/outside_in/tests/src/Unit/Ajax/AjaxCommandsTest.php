<?php

namespace Drupal\Tests\outside_in\Unit\Ajax;

use Drupal\Tests\UnitTestCase;

/**
 * Test coverage for various classes in the \Drupal\Core\Ajax namespace.
 *
 * @group Ajax
 */
class AjaxCommandsTest extends UnitTestCase {

  /**
   * @covers \Drupal\Core\Ajax\OpenCanvasDialogCommand
   */
  public function testOpenCanvasDialogCommand() {
    $command = $this->getMockBuilder('Drupal\Core\Ajax\OpenCanvasDialogCommand')
      ->setConstructorArgs(array(
        'Title', '<p>Text!</p>', array(
          'url' => 'example',
        ),
      ))
      ->setMethods(array('getRenderedContent'))
      ->getMock();

    // This method calls the render service, which isn't available. We want it
    // to do nothing so we mock it to return a known value.
    $command->expects($this->once())
      ->method('getRenderedContent')
      ->willReturn('rendered content');

    $expected = array(
      'command' => 'openOffCanvas',
      'selector' => '#drupal-offcanvas',
      'settings' => NULL,
      'data' => 'rendered content',
      'dialogOptions' => array(
        'url' => 'example',
        'title' => 'Title',
        'modal' => FALSE,
      ),
    );
    $this->assertEquals($expected, $command->render());
  }

}

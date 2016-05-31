<?php

namespace Drupal\block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\simpletest\BlockCreationTrait;

/**
 * Tests block configure modal links.
 */
class BlockModalTest extends JavascriptTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'block', 'contextual'];
  /** @var \Behat\Mink\Driver\DriverInterface  */
  protected $driver;

  protected function setUp() {
    parent::setUp();
    //$this->placeBlock('system_powered_by_block', ['id' => 'content']);
    $permissions = [

    ];
    //$this->driver = $this->getSession()->getDriver();
  }

  public function testConfigureBlock() {
    //$this->drupalGet('node');
    //$session = $this->getSession();

    //$this->assertElementPresent('.contextual-links a.use-ajax[data-dialog-type="modal"]');
  }

}

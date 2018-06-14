<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests recursion prevention in layouts.
 */
class LayoutRecursionTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'views',
    'layout_builder',
    'block',
    'node',
    'layout_builder_recursive_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');


    // @todo Find balance.
    // when creating 5 nodes here
    // \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::RECURSIVE_RENDER_LIMIT
    // has to be 1.
    for ($i = 1; $i <= 5; $i++) {
      Node::create([
        'type' => 'type_with_recursion',
        'title' => "Title $i",
        'body' => "Body text $i",
      ])->save();
    }
  }

  public function testRecursionPrevention() {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
    ]));

    $this->drupalGet('node/1');
    file_put_contents('/Users/ted.bowman/Sites/www/test.html', $this->getSession()->getPage()->getOuterHtml());
    $this->assertSession()->pageTextContains("Title 1");

  }
}

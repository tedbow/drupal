<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;

class DisableOverridesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'field_ui',
    'layout_builder',
    //'layout_test',
    'node',
    'quickedit',

  ];

  /**
   * The node to customize with Layout Builder.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'new_revision' => FALSE,
      ]
    );
    $this->node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'access content',
      'access in-place editing',
    ], 'foobar'));
  }

  public function testDisableOverides() {
    $node = $this->node;
    $this->drupalGetRe($node);

    $this->enableLayouts('admin/structure/types/manage/bundle_with_section_field/display/default');
    $this->drupalGetRe($node);
    // Test article with Layout Builder override.
    $this->createLayoutOverride('node/' . $node->id() . '/layout');
    $this->drupalGetRe($node);
    $this->revertLayoutToDefaults('node/' . $node->id() . '/layout');

    $this->disableLayoutBuilder('admin/structure/types/manage/bundle_with_section_field/display/default');


  }

  /**
   * Disable layout builder.
   *
   * @param string $path
   *   The path to the manage display page.
   */
  protected function disableLayoutBuilder($path) {
    $page = $this->getSession()->getPage();
    $this->drupalGet($path);
    $page->uncheckField('layout[allow_custom]');
    $page->uncheckField('layout[enabled]');
    $page->pressButton('Save');
    // @todo why no confirm here?
    // $this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);
    $page->pressButton('Confirm');
  }

  /**
   * Revert a layout override.
   *
   * @param string $path
   *   The path for the layout builder.
   */
  protected function revertLayoutToDefaults($path) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->drupalGet($path);
    $assert_session->buttonExists('Revert to defaults');
    $page->pressButton('Revert to defaults');
    $page->pressButton('Revert');
    $assert_session->pageTextContains('The layout has been reverted back to defaults.');
    // $this->assertSession()->waitForElementVisible('css', '.go',898998989898989989898989989898998);
  }

  /**
   * @todo.
   *
   * @param string $layout_url
   */
  protected function createLayoutOverride($layout_url) {
    $assert_session = $this->assertSession();

    $this->drupalGet($layout_url);
    $assert_session->buttonExists('Save layout');
    $this->getSession()->getPage()->pressButton('Save layout');
    $this->assertNotEmpty($assert_session->waitForElement('css', '.messages--status'));
    $assert_session->pageTextContains('The layout override has been saved.');
  }

  /**
   * @todo.
   */
  protected function enableLayouts($path) {

    $page = $this->getSession()->getPage();
    $this->drupalGet($path);
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');
  }

  private function drupalGetRe($node) {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $node = Node::load($node->id());
    $this->drupalGet('node/' . $this->node->id());
  }
}

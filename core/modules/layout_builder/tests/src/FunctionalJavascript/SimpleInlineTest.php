<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

/**
 * @group layout_builder
 */
class SimpleInlineTest extends InlineBlockTestBase {

  public function testSimple() {
    /** @var \Drupal\Core\Cron $cron */
    $cron = \Drupal::service('cron');
    $this->drupalLogin($this->drupalCreateUser([
      'administer content types',
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'administer nodes',
      'bypass node access',
    ]));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add a block to default layout.
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $this->drupalGet($field_ui_prefix . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");
    $this->assertSaveLayout();

  }

}

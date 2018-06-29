<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Test access to private files in block fields on the Layout Builder.
 *
 * @group layout_builder
 */
class InlineBlockPrivateFilesTest extends InlineBlockTestBase {

  use FileFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'file',
  ];

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $field_settings = [
      'file_extensions' => 'txt',
      'uri_scheme' => 'private',
    ];
    $this->createFileField('field_file', 'block_content', 'basic', $field_settings);
    $this->fileSystem = $this->container->get('file_system');


  }

  /**
   * Test access to private files added via inline blocks in the layout builder.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPrivateFiles() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));


    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    // Enable overrides.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalLogout();

    // Log in as user you can just configure layouts.
    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'access content',
    ]));
    $this->drupalGet('node/1/layout');

    // Create a new file entity.
    $file = File::create([
      'uid' => 1,
      'filename' => 'drupal.txt',
      'uri' => 'private://drupal.txt',
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ]);
    file_put_contents($file->getFileUri(), 'The secret in file');

    // Save it, inserting a new record.
    $file->save();

    $this->addInlineFileBlockToLayout('The file', $file);

    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    file_put_contents('/Users/ted.bowman/Sites/www/file.html', $page->getOuterHtml());
    $assert_session->linkExists($file->label());
    $private_href = $page->findLink($file->label())->getAttribute('href');
    $page->clickLink($file->label());
    $assert_session->pageTextContains('The secret in file');

    // Access file directly.
    $this->drupalGet($private_href);
    $assert_session->pageTextContains('The secret in file');

    $this->drupalGet('node/1/layout');
    $this->removeInlineBlockFromLayout();
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains($file->label());
    // Try to access file directly after it has been removed.
    $this->drupalGet($private_href);
    $assert_session->pageTextContains('The secret in file');
  }

  /**
   * Adds an entity block with a file.
   *
   * @param string $title
   *   The title field value.
   * @param \Drupal\file\Entity\File $file
   *   The file entity.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function addInlineFileBlockToLayout($title, File $file) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.block-categories details:contains(Create new block)'));
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue($title);
    $page->attachFileToField("files[settings_block_form_field_file_0]", $this->fileSystem->realpath($file->getFileUri()));
    $page->pressButton('Add Block');
    // @todo Replace with 'assertNoElementAfterWait()' after
    // https://www.drupal.org/project/drupal/issues/2892440.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');
    $found_new_text = FALSE;
    /** @var \Behat\Mink\Element\NodeElement $element */
    foreach ($page->findAll('css', static::INLINE_BLOCK_LOCATOR) as $element) {
      if (stristr($element->getText(), $file->label())) {
        $found_new_text = TRUE;
        break;
      }
    }
    $this->assertNotEmpty($found_new_text, 'Found block text on page.');
  }

}

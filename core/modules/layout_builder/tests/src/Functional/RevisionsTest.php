<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the Layout Builder revisions behavior.
 *
 * @group layout_builder
 */
class RevisionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create two content types.
    $this->createContentType(['type' => 'bundle_with_revisions', 'new_revision' => TRUE]);
    $this->createContentType(['type' => 'bundle_without_revisions', 'new_revision' => FALSE]);
  }

  /**
   * Tests that default revision settings are respected.
   */
  public function testRevisions() {
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'administer user display',
      'administer user fields',
    ]));
    $bundle_field_ui_prefixes = [
      'admin/structure/types/manage/bundle_with_revisions',
      'admin/structure/types/manage/bundle_without_revisions',
      'admin/config/people/accounts',
    ];
    foreach ($bundle_field_ui_prefixes as $prefix) {
      $this->drupalPostForm("$prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');
    }
    // Create a node of the bundle that will have new revisions by default.
    $revision_node = $this->createNode([
      'type' => 'bundle_with_revisions',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);
    // Create a node of the bundle that will NOT have new revisions by default.
    $no_revisions_node = $this->createNode([
      'type' => 'bundle_without_revisions',
      'title' => 'The second node title',
      'body' => [
        [
          'value' => 'The second node body',
        ],
      ],
    ]);
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Ensure that saving a layout for a node of the bundle that does save a new
    // revision by default does create a new revision.
    $revision_id_before_new_revision = $node_storage->getLatestRevisionId($revision_node->id());
    $this->saveLayoutOverride($revision_node);
    $this->assertGreaterThan($revision_id_before_new_revision, $node_storage->getLatestRevisionId($revision_node->id()));

    // Ensure that saving a layout for a node of the bundle that does NOT save a
    // new revision by default does NOT create a new revision.
    $revision_id_before_save_same_revision = $node_storage->getLatestRevisionId($no_revisions_node->id());
    $this->saveLayoutOverride($no_revisions_node);
    $this->assertEquals($revision_id_before_save_same_revision, $node_storage->getLatestRevisionId($no_revisions_node->id()));

    // Save an override on a non-revisionable entity.
    $this->saveLayoutOverride(User::load(1));
  }

  /**
   * Saves a layout override for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function saveLayoutOverride(EntityInterface $entity) {
    $this->drupalGet($entity->toUrl()->toString() . "/layout");
    $this->clickLink('Save Layout');
    $this->assertSession()->pageTextContains('The layout override has been saved.');
  }

}

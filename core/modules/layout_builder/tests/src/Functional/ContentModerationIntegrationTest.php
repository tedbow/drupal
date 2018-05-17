<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\content_moderation\Permissions;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

class ContentModerationIntegrationTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'views',
    'layout_builder',
    'layout_builder_views_test',
    'layout_test',
    'block',
    'node',
    'layout_builder_test',
    'content_moderation',
    'workflows',
  ];


  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create two nodes.
    //$this->createContentType(['type' => 'bundle_with_section_field']);
  }

  public function testLayoutForwardRevision() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $permission = new Permissions();
    $permissions = array_keys($permission->transitionPermissions());

    $this->drupalLogin($this->drupalCreateUser(
      [
        'configure any layout',
        'administer node display',
        'administer node fields',
        'administer workflows',
        'view any unpublished content',
        'view latest version',
        'view own unpublished content',
        'access content',
        'view all revisions',
        'administer nodes',
        'administer content types'
      ] + $permissions
    ));

    $this->createContentTypeFromUi('Moderated content', 'bundle_with_section_field', TRUE);
    $this->grantUserPermissionToCreateContentOfType($this->loggedInUser, 'bundle_with_section_field');



    $this->drupalPostForm(
      'admin/config/workflow/workflows/manage/editorial/type/node',
      [
        'bundles[bundle_with_section_field]' => 1,
      ],
      'Save'
    );
    // Ensure the parent environment is up-to-date.
    // @see content_moderation_workflow_insert()
    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
    $router_builder = $this->container->get('router.builder');
    $router_builder->rebuildIfNeeded();
    $this->drupalGet('/node/add/bundle_with_section_field');
    file_put_contents('/Users/ted.bowman/Sites/www/add.html', $page->getOuterHtml());
    return;

    $this->drupalPostForm(
      '/node/add/bundle_with_section_field',
      [
        'title[0][value]' => 'The title',
        'moderation_state[0][state]' => 'draft',
      ],
      'Save');
    file_put_contents('/Users/ted.bowman/Sites/www/sa.html', $page->getOuterHtml());

    $this->drupalGet('/node/1');
    file_put_contents('/Users/ted.bowman/Sites/www/s.html', $page->getOuterHtml());
    $this->drupalGet('/node/1/edit');
    file_put_contents('/Users/ted.bowman/Sites/www/s2.html', $page->getOuterHtml());
    $this->drupalPostForm(NULL, ['new_state'=> 'published'], 'Apply');

    //$this->drupalPostForm('/')

  }

  /**
   * Enable moderation for a specified content type, using the UI.
   *
   * @param string $content_type_id
   *   Machine name.
   * @param string $workflow_id
   *   The workflow to attach to the bundle.
   */
  public function enableModerationThroughUi($content_type_id, $workflow_id = 'editorial') {
    $this->drupalGet('/admin/config/workflow/workflows');
    $this->assertLinkByHref('admin/config/workflow/workflows/manage/' . $workflow_id);
    $edit['bundles[' . $content_type_id . ']'] = TRUE;
    $this->drupalPostForm('admin/config/workflow/workflows/manage/' . $workflow_id . '/type/node', $edit, t('Save'));
    // Ensure the parent environment is up-to-date.
    // @see content_moderation_workflow_insert()
    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
    $router_builder = $this->container->get('router.builder');
    $router_builder->rebuildIfNeeded();
  }

  /**
   * Creates a content-type from the UI.
   *
   * @param string $content_type_name
   *   Content type human name.
   * @param string $content_type_id
   *   Machine name.
   * @param bool $moderated
   *   TRUE if should be moderated.
   * @param string $workflow_id
   *   The workflow to attach to the bundle.
   */
  protected function createContentTypeFromUi($content_type_name, $content_type_id, $moderated = FALSE, $workflow_id = 'editorial') {
    $this->drupalGet('admin/structure/types');
    $this->clickLink('Add content type');

    // Check that the 'Create new revision' checkbox is checked and disabled.
    $this->assertSession()->checkboxChecked('options[revision]');
    $this->assertSession()->fieldDisabled('options[revision]');

    $edit = [
      'name' => $content_type_name,
      'type' => $content_type_id,
    ];
    file_put_contents('/Users/ted.bowman/Sites/www/typeAdd.html', $this->getSession()->getPage()->getOuterHtml());
    $this->drupalPostForm(NULL, $edit, t('Save content type'));

    // Check the content type has been set to create new revisions.
    $this->assertTrue(NodeType::load($content_type_id)->isNewRevision());

    if ($moderated) {
      $this->enableModerationThroughUi($content_type_id, $workflow_id);
    }
  }
  /**
   * Grants given user permission to create content of given type.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User to grant permission to.
   * @param string $content_type_id
   *   Content type ID.
   */
  protected function grantUserPermissionToCreateContentOfType(AccountInterface $account, $content_type_id) {
    $role_ids = $account->getRoles(TRUE);
    /* @var \Drupal\user\RoleInterface $role */
    $role_id = reset($role_ids);
    $role = Role::load($role_id);
    $role->grantPermission(sprintf('create %s content', $content_type_id));
    $role->grantPermission(sprintf('edit any %s content', $content_type_id));
    $role->grantPermission(sprintf('delete any %s content', $content_type_id));
    $role->save();
  }

}

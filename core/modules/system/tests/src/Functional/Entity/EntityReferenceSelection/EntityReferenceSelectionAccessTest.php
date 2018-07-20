<?php

namespace Drupal\Tests\system\Functional\Entity\EntityReferenceSelection;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\block_content\Plugin\EntityReferenceSelection\BlockContentSelection;
use Drupal\block_content_test\Plugin\EntityReferenceSelection\TestSelection;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Language\LanguageInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\comment\Entity\Comment;

/**
 * Tests for the base handlers provided by Entity Reference.
 *
 * @group entity_reference
 */
class EntityReferenceSelectionAccessTest extends BrowserTestBase {

  use CommentTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'comment', 'block_content', 'block_content_test'];

  protected function setUp() {
    parent::setUp();

    // Create an Article node type.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Checks that a selection plugin returns the expected results.
   *
   * @param array $selection_options
   *   An array of options as required by entity reference selection plugins.
   * @param array $tests
   *   An array of tests to run.
   * @param string $handler_name
   *   The name of the entity type selection handler being tested.
   */
  protected function assertReferenceable(array $selection_options, $tests, $handler_name) {
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($selection_options);

    foreach ($tests as $test) {
      foreach ($test['arguments'] as $arguments) {
        $result = call_user_func_array([$handler, 'getReferenceableEntities'], $arguments);
        $this->assertEqual($result, $test['result'], format_string('Valid result set returned by @handler.', ['@handler' => $handler_name]));

        $result = call_user_func_array([$handler, 'countReferenceableEntities'], $arguments);
        if (!empty($test['result'])) {
          $bundle = key($test['result']);
          $count = count($test['result'][$bundle]);
        }
        else {
          $count = 0;
        }

        $this->assertEqual($result, $count, format_string('Valid count returned by @handler.', ['@handler' => $handler_name]));
      }
    }
  }

  /**
   * Test the node-specific overrides of the entity handler.
   */
  public function testNodeHandler() {
    $selection_options = [
      'target_type' => 'node',
      'handler' => 'default',
      'target_bundles' => NULL,
    ];

    // Build a set of test data.
    // Titles contain HTML-special characters to test escaping.
    $node_values = [
      'published1' => [
        'type' => 'article',
        'status' => NodeInterface::PUBLISHED,
        'title' => 'Node published1 (<&>)',
        'uid' => 1,
      ],
      'published2' => [
        'type' => 'article',
        'status' => NodeInterface::PUBLISHED,
        'title' => 'Node published2 (<&>)',
        'uid' => 1,
      ],
      'unpublished' => [
        'type' => 'article',
        'status' => NodeInterface::NOT_PUBLISHED,
        'title' => 'Node unpublished (<&>)',
        'uid' => 1,
      ],
    ];

    $nodes = [];
    $node_labels = [];
    foreach ($node_values as $key => $values) {
      $node = Node::create($values);
      $node->save();
      $nodes[$key] = $node;
      $node_labels[$key] = Html::escape($node->label());
    }

    // Test as a non-admin.
    $normal_user = $this->drupalCreateUser(['access content']);
    \Drupal::currentUser()->setAccount($normal_user);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'article' => [
            $nodes['published1']->id() => $node_labels['published1'],
            $nodes['published2']->id() => $node_labels['published2'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['published1', 'CONTAINS'],
          ['Published1', 'CONTAINS'],
        ],
        'result' => [
          'article' => [
            $nodes['published1']->id() => $node_labels['published1'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['published2', 'CONTAINS'],
          ['Published2', 'CONTAINS'],
        ],
        'result' => [
          'article' => [
            $nodes['published2']->id() => $node_labels['published2'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['invalid node', 'CONTAINS'],
        ],
        'result' => [],
      ],
      [
        'arguments' => [
          ['Node unpublished', 'CONTAINS'],
        ],
        'result' => [],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'Node handler');

    // Test as an admin.
    $admin_user = $this->drupalCreateUser(['access content', 'bypass node access']);
    \Drupal::currentUser()->setAccount($admin_user);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'article' => [
            $nodes['published1']->id() => $node_labels['published1'],
            $nodes['published2']->id() => $node_labels['published2'],
            $nodes['unpublished']->id() => $node_labels['unpublished'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['Node unpublished', 'CONTAINS'],
        ],
        'result' => [
          'article' => [
            $nodes['unpublished']->id() => $node_labels['unpublished'],
          ],
        ],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'Node handler (admin)');
  }

  /**
   * Test the user-specific overrides of the entity handler.
   */
  public function testUserHandler() {
    $selection_options = [
      'target_type' => 'user',
      'handler' => 'default',
      'target_bundles' => NULL,
      'include_anonymous' => TRUE,
    ];

    // Build a set of test data.
    $user_values = [
      'anonymous' => User::load(0),
      'admin' => User::load(1),
      'non_admin' => [
        'name' => 'non_admin <&>',
        'mail' => 'non_admin@example.com',
        'roles' => [],
        'pass' => user_password(),
        'status' => 1,
      ],
      'blocked' => [
        'name' => 'blocked <&>',
        'mail' => 'blocked@example.com',
        'roles' => [],
        'pass' => user_password(),
        'status' => 0,
      ],
    ];

    $user_values['anonymous']->name = $this->config('user.settings')->get('anonymous');
    $users = [];

    $user_labels = [];
    foreach ($user_values as $key => $values) {
      if (is_array($values)) {
        $account = User::create($values);
        $account->save();
      }
      else {
        $account = $values;
      }
      $users[$key] = $account;
      $user_labels[$key] = Html::escape($account->getUsername());
    }

    // Test as a non-admin.
    \Drupal::currentUser()->setAccount($users['non_admin']);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'user' => [
            $users['admin']->id() => $user_labels['admin'],
            $users['non_admin']->id() => $user_labels['non_admin'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['non_admin', 'CONTAINS'],
          ['NON_ADMIN', 'CONTAINS'],
        ],
        'result' => [
          'user' => [
            $users['non_admin']->id() => $user_labels['non_admin'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['invalid user', 'CONTAINS'],
        ],
        'result' => [],
      ],
      [
        'arguments' => [
          ['blocked', 'CONTAINS'],
        ],
        'result' => [],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'User handler');

    \Drupal::currentUser()->setAccount($users['admin']);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'user' => [
            $users['anonymous']->id() => $user_labels['anonymous'],
            $users['admin']->id() => $user_labels['admin'],
            $users['non_admin']->id() => $user_labels['non_admin'],
            $users['blocked']->id() => $user_labels['blocked'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['blocked', 'CONTAINS'],
        ],
        'result' => [
          'user' => [
            $users['blocked']->id() => $user_labels['blocked'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['Anonymous', 'CONTAINS'],
          ['anonymous', 'CONTAINS'],
        ],
        'result' => [
          'user' => [
            $users['anonymous']->id() => $user_labels['anonymous'],
          ],
        ],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'User handler (admin)');

    // Test the 'include_anonymous' option.
    $selection_options['include_anonymous'] = FALSE;
    $referenceable_tests = [
      [
        'arguments' => [
          ['Anonymous', 'CONTAINS'],
          ['anonymous', 'CONTAINS'],
        ],
        'result' => [],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'User handler (does not include anonymous)');

    // Check that the Anonymous user is not included in the results when no
    // label matching is done, for example when using the 'options_select'
    // widget.
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL],
        ],
        'result' => [
          'user' => [
            $users['admin']->id() => $user_labels['admin'],
            $users['non_admin']->id() => $user_labels['non_admin'],
            $users['blocked']->id() => $user_labels['blocked'],
          ],
        ],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'User handler (does not include anonymous)');
  }

  /**
   * Test the comment-specific overrides of the entity handler.
   */
  public function testCommentHandler() {
    $selection_options = [
      'target_type' => 'comment',
      'handler' => 'default',
      'target_bundles' => NULL,
    ];

    // Build a set of test data.
    $node_values = [
      'published' => [
        'type' => 'article',
        'status' => 1,
        'title' => 'Node published',
        'uid' => 1,
      ],
      'unpublished' => [
        'type' => 'article',
        'status' => 0,
        'title' => 'Node unpublished',
        'uid' => 1,
      ],
    ];
    $nodes = [];
    foreach ($node_values as $key => $values) {
      $node = Node::create($values);
      $node->save();
      $nodes[$key] = $node;
    }

    // Create comment field on article.
    $this->addDefaultCommentField('node', 'article');

    $comment_values = [
      'published_published' => [
        'entity_id' => $nodes['published']->id(),
        'entity_type' => 'node',
        'field_name' => 'comment',
        'uid' => 1,
        'cid' => NULL,
        'pid' => 0,
        'status' => CommentInterface::PUBLISHED,
        'subject' => 'Comment Published <&>',
        'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ],
      'published_unpublished' => [
        'entity_id' => $nodes['published']->id(),
        'entity_type' => 'node',
        'field_name' => 'comment',
        'uid' => 1,
        'cid' => NULL,
        'pid' => 0,
        'status' => CommentInterface::NOT_PUBLISHED,
        'subject' => 'Comment Unpublished <&>',
        'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ],
      'unpublished_published' => [
        'entity_id' => $nodes['unpublished']->id(),
        'entity_type' => 'node',
        'field_name' => 'comment',
        'uid' => 1,
        'cid' => NULL,
        'pid' => 0,
        'status' => CommentInterface::NOT_PUBLISHED,
        'subject' => 'Comment Published on Unpublished node <&>',
        'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ],
    ];

    $comments = [];
    $comment_labels = [];
    foreach ($comment_values as $key => $values) {
      $comment = Comment::create($values);
      $comment->save();
      $comments[$key] = $comment;
      $comment_labels[$key] = Html::escape($comment->label());
    }

    // Test as a non-admin.
    $normal_user = $this->drupalCreateUser(['access content', 'access comments']);
    \Drupal::currentUser()->setAccount($normal_user);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'comment' => [
            $comments['published_published']->cid->value => $comment_labels['published_published'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['Published', 'CONTAINS'],
        ],
        'result' => [
          'comment' => [
            $comments['published_published']->cid->value => $comment_labels['published_published'],
          ],
        ],
      ],
      [
        'arguments' => [
          ['invalid comment', 'CONTAINS'],
        ],
        'result' => [],
      ],
      [
        'arguments' => [
          ['Comment Unpublished', 'CONTAINS'],
        ],
        'result' => [],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'Comment handler');

    // Test as a comment admin.
    $admin_user = $this->drupalCreateUser(['access content', 'access comments', 'administer comments']);
    \Drupal::currentUser()->setAccount($admin_user);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'comment' => [
            $comments['published_published']->cid->value => $comment_labels['published_published'],
            $comments['published_unpublished']->cid->value => $comment_labels['published_unpublished'],
          ],
        ],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'Comment handler (comment admin)');

    // Test as a node and comment admin.
    $admin_user = $this->drupalCreateUser(['access content', 'access comments', 'administer comments', 'bypass node access']);
    \Drupal::currentUser()->setAccount($admin_user);
    $referenceable_tests = [
      [
        'arguments' => [
          [NULL, 'CONTAINS'],
        ],
        'result' => [
          'comment' => [
            $comments['published_published']->cid->value => $comment_labels['published_published'],
            $comments['published_unpublished']->cid->value => $comment_labels['published_unpublished'],
            $comments['unpublished_published']->cid->value => $comment_labels['unpublished_published'],
          ],
        ],
      ],
    ];
    $this->assertReferenceable($selection_options, $referenceable_tests, 'Comment handler (comment + node admin)');
  }

  /**
   * Test the comment-specific overrides of the entity handler.
   *
   * @dataProvider blockContentProvider
   *
   * @throws \Exception
   */
  public function testBlockContentHandlers($custom_selection_plugin, $condition_type, $is_reusable, $expectation) {
    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'spiffy',
      'label' => 'Mucho spiffy',
      'description' => "Provides a block type that increases your site's spiffiness by up to 11%",
    ]);
    $block_content_type->save();

    // And reusable block content entities.
    $block_reusable = BlockContent::create([
      'info' => 'Reusable Block',
      'type' => 'spiffy',
    ]);
    $block_reusable->save();
    $block_non_reusable = BlockContent::create([
      'info' => 'Non-reusable Block',
      'type' => 'spiffy',
      'reusable' => FALSE,
    ]);
    $block_non_reusable->save();


    // Setup the 3 expectation cases.
    $expectations = [
      'both_blocks' => [
        'spiffy' => [
          $block_reusable->id() => $block_reusable->label(),
          $block_non_reusable->id() => $block_non_reusable->label(),
        ],
      ],
      'block_reusable' => ['spiffy' => [$block_reusable->id() => $block_reusable->label()]],
      'block_non_reusable' => ['spiffy' => [$block_non_reusable->id() => $block_non_reusable->label()]],
    ];
    $configuration = [
      'target_type' => 'block_content',
      'target_bundles' => ['spiffy' => 'spiffy'],
      'sort' => ['field' => '_none'],
    ];

    if ($custom_selection_plugin) {
      // For selection plugins besides BlockContentSelection the queries will be
      // altered to not include non-reusable blocks unless a condition on the
      // 'reusable' field is explicitly set.
      // @see block_content_query_entity_reference_alter()
      $selectionHandler = new TestSelection($configuration, '', '', $this->container->get('entity.manager'), $this->container->get('module_handler'), \Drupal::currentUser());
      // Set the test mode so that the conditions will be set at different
      // nested levels and with different values.
      $selectionHandler->setTestMode($condition_type, $is_reusable);
    }
    else {
      $selectionHandler = new BlockContentSelection($configuration, '', '', $this->container->get('entity.manager'), $this->container->get('module_handler'), \Drupal::currentUser());
    }

    $this->assertEquals(
      $expectations[$expectation],
      $selectionHandler->getReferenceableEntities()
    );
  }

  /**
   * Dataprovider for blockContentProvider().
   */
  public function blockContentProvider() {
    $cases['block_content_selection_plugin'] = [
      FALSE,
      NULL,
      NULL,
      'block_reusable',
    ];
    $cases['custom_selection_plugin:no_condition'] = [
      TRUE,
      NULL,
      NULL,
      'block_reusable',
    ];
    foreach (['base', 'group', 'nested_group'] as $condition_type) {
      foreach ([TRUE, FALSE] as $reusable) {
        $cases["custom_selection_plugin:$condition_type:" . ($reusable ? 'reusable' : 'non-reusable')] = [
          TRUE,
          $condition_type,
          $reusable,
          $reusable ? 'block_reusable' : 'block_non_reusable',
        ];
      }
    }
    return $cases;
  }

}

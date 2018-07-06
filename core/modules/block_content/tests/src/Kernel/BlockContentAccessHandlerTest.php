<?php

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\BlockContentAccessControlHandler;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\block_content\BlockContentAccessControlHandler
 * @group block_content
 */
class BlockContentAccessHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'system',
    'user',
  ];

  /**
   * The BlockContent access controller to test.
   *
   * @var \Drupal\block_content\BlockContentAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * The mock module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


  /**
   * The BlockContent entity used for testing.
   *
   * @var \Drupal\block_content\Entity\BlockContent
   */
  protected $blockEntity;

  protected function setUp() {
    parent::setUp();
    $this->installSchema('system', ['sequence']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');

    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'square',
      'label' => 'A square block type',
      'description' => "Provides a block type that increases your site's spiffiness by up to 11%",
    ]);
    $block_content_type->save();

    $this->blockEntity = BlockContent::create([
      'info' => 'The Block',
      'type' => 'square',
    ]);
    $this->blockEntity->save();
    User::create([
      'name' => 'admin',
    ])->save();
    $this->accessControlHandler = new BlockContentAccessControlHandler(\Drupal::entityTypeManager()->getDefinition('block_content'), \Drupal::service('event_dispatcher'));
  }


  /**
   * @dataProvider providerTestAccess
   */
  public function testAccess($published, $reusable, $permissions, $parent_access, $expected_access) {
    $published ? $this->blockEntity->setPublished() : $this->blockEntity->setUnpublished();
    $this->blockEntity->setReusable($reusable);

    $role = Role::create([
      'id' => 'roly',
      'label' => 'roly poly',
    ]);
    $role->save();
    $user = User::create([
      'name' => 'Someone',
      'mail' => 'hi@example.com',
    ]);
    $user->save();

    if ($permissions) {

      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
    $user->addRole($role->id());

    if ($parent_access) {
      $parent_entity = $this->prophesize(AccessibleInterface::class);
      $expected_parent_result = NULL;
      switch ($parent_access) {
        case 'allowed':
          $expected_parent_result = AccessResult::allowed();
          break;

        case 'neutral':
          $expected_parent_result = AccessResult::neutral();
          break;

        case 'forbidden':
          $expected_parent_result = AccessResult::forbidden();
          break;
      }
      $parent_entity->access('view', $user, TRUE)
        ->willReturn($expected_parent_result)
        ->shouldBeCalled();

      $this->blockEntity->setAccessDependency($parent_entity->reveal());

    }
    $this->blockEntity->save();

    $result = $this->accessControlHandler->access($this->blockEntity, 'view', $user, TRUE);
    $this->assertAccess($expected_access, $result);
  }

  public function providerTestAccess() {
    return [
      'published:reusable' => [
        TRUE,
        TRUE,
        [],
        NULL,
        'allowed',

      ],
      'unpublished:reusable' => [
        FALSE,
        TRUE,
        [],
        NULL,
        'neutral',
      ],
      'unpublished:reusable:admin' => [
        FALSE,
        TRUE,
        ['administer blocks'],
        NULL,
        'allowed',
      ],
      'published:reusable:admin' => [
        TRUE,
        TRUE,
        ['administer blocks'],
        NULL,
        'allowed',
      ],
      'published:non_reusable' => [
        TRUE,
        FALSE,
        [],
        NULL,
        'forbidden',
      ],
      'published:non_reusable:parent_allowed' => [
        TRUE,
        FALSE,
        [],
        'allowed',
        'allowed',
      ],
      'published:non_reusable:parent_neutral' => [
        TRUE,
        FALSE,
        [],
        'neutral',
        'neutral',
      ],
      'published:non_reusable:parent_forbidden' => [
        TRUE,
        FALSE,
        [],
        'forbidden',
        'forbidden',
      ],
    ];
  }

  /**
   * @param $expected_access
   * @param $result
   */
  protected function assertAccess($expected_access, AccessResultInterface $result) {
    switch ($expected_access) {
      case 'allowed':
        $this->assertTrue($result->isAllowed());
        break;

      case 'forbidden':
        $this->assertTrue($result->isForbidden());
        break;

      case  'neutral':
        $this->assertTrue($result->isNeutral());
        break;

      default:
        $this->fail('Unexpected access type');
    }
  }
}

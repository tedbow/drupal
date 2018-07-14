<?php

namespace Drupal\Tests\Core\Access;

use Drupal\Core\Access\AccessGroupAnd;
use Drupal\Core\Access\AccessGroupOr;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests accessible groups.
 *
 * @group Access
 */
class AccessGroupTest extends UnitTestCase {

  use AccessibleTestingTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->account = $this->prophesize(AccountInterface::class)->reveal();
  }

  /**
   * @covers \Drupal\Core\Access\AccessGroupAnd
   * @covers \Drupal\Core\Access\AccessGroupOr
   */
  public function testGroups() {
    $allowedAccessible = $this->createAccessibleDouble(AccessResult::allowed());
    $forbiddenAccessible = $this->createAccessibleDouble(AccessResult::forbidden());
    $neutralAccessible = $this->createAccessibleDouble(AccessResult::neutral());

    // Ensure that groups with no dependencies return a forbidden access result.
    $this->assertTrue((new AccessGroupOr())->access('view', $this->account, TRUE)->isForbidden());
    $this->assertTrue((new AccessGroupAnd())->access('view', $this->account, TRUE)->isForbidden());

    $orForbidden = new AccessGroupOr();
    $orForbidden->addDependency($allowedAccessible)->addDependency($forbiddenAccessible);
    $this->assertTrue($orForbidden->access('view', $this->account, TRUE)->isForbidden());

    $orAllowed = new AccessGroupOr();
    $orAllowed->addDependency($allowedAccessible)->addDependency($neutralAccessible);
    $this->assertTrue($orAllowed->access('view', $this->account, TRUE)->isAllowed());

    $andNeutral = new AccessGroupAnd();
    $andNeutral->addDependency($allowedAccessible)->addDependency($neutralAccessible);
    $this->assertTrue($andNeutral->access('view', $this->account, TRUE)->isNeutral());

    // We can also add groups and dependencies!!!!! Nested!!!!!
    $andNeutral->addDependency($orAllowed);
    $this->assertTrue($andNeutral->access('view', $this->account, TRUE)->isNeutral());

    $andForbidden = $andNeutral;
    $andForbidden->addDependency($forbiddenAccessible);
    $this->assertTrue($andForbidden->access('view', $this->account, TRUE)->isForbidden());

    // We can make groups from other groups!
    $andGroupsForbidden = new AccessGroupAnd();
    $andGroupsForbidden->addDependency($andNeutral)->addDependency($andForbidden)->addDependency($orForbidden);
    $this->assertTrue($andGroupsForbidden->access('view', $this->account, TRUE)->isForbidden());
    // But then would could also add a non-group accessible.
    $andGroupsForbidden->addDependency($allowedAccessible);
    $this->assertTrue($andGroupsForbidden->access('view', $this->account, TRUE)->isForbidden());
  }

}

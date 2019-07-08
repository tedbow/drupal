<?php

namespace Drupal\Tests\Component\Version;

use Drupal\Component\Version\Constraint;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\Component\Version\Constraint
 * @group Version
 */
class ConstraintTest extends TestCase {

  /**
   * @covers ::isCompatible
   * @dataProvider providerIsCompatible
   */
  public function testIsCompatible(Constraint $version_info, $current_version, $result) {
    $this->assertSame($result, $version_info->isCompatible($current_version));
  }

  /**
   * Provider for testIsCompatible.
   */
  public function providerIsCompatible() {
    $tests = [];

    $tests['no-dependencies'] = [new Constraint('', '8.x'), '8.1.x', TRUE];

    foreach (['', '=', '=='] as $equal_operator) {
      foreach (['!=', '<>'] as $not_equals_operator) {
        foreach (['', ' '] as $space) {
          // Stable version.
          $constraints = "{$equal_operator}{$space}8.x-1.0";
          $stable = new Constraint($constraints, '8.x');
          $tests["($constraints)-1.0"] = [$stable, '1.0', TRUE];
          $tests["($constraints)-1.1"] = [$stable, '1.1', FALSE];
          $tests["($constraints)-0.9"] = [$stable, '0.9', FALSE];

          // Alpha version.
          $constraints = "{$equal_operator}{$space}8.x-1.1-alpha12";
          $alpha = new Constraint($constraints, '8.x');
          $tests["($constraints)-alpha12"] = [$alpha, '1.1-alpha12', TRUE];
          $tests["($constraints)-alpha10"] = [$alpha, '1.1-alpha10', FALSE];
          $tests["($constraints)-beta1"] = [$alpha, '1.1-beta1', FALSE];

          // Beta version.
          $constraints = "{$equal_operator}{$space}8.x-1.1-beta8";
          $beta = new Constraint($constraints, '8.x');
          $tests["($constraints)-beta8"] = [$beta, '1.1-beta8', TRUE];
          $tests["($constraints)-beta4"] = [$beta, '1.1-beta4', FALSE];

          // RC version.
          $constraints = "{$equal_operator}{$space}8.x-1.1-rc11";
          $rc = new Constraint($constraints, '8.x');
          $tests["($constraints)-rc11"] = [$rc, '1.1-rc11', TRUE];
          $tests["($constraints)-rc2"] = [$rc, '1.1-rc2', FALSE];

          // Test greater than.
          $constraints = ">{$space}8.x-1.x";
          $greater = new Constraint($constraints, '8.x');
          $tests["($constraints)-2.0"] = [$greater, '2.0', TRUE];
          $tests["($constraints)-1.1"] = [$greater, '1.1', FALSE];
          $tests["($constraints)-0.9"] = [$greater, '0.9', FALSE];

          // Test greater than or equal.
          $constraints = ">={$space}8.x-1.0";
          $greater_or_equal = new Constraint($constraints, '8.x');
          $tests["($constraints)-1.1"] = [$greater_or_equal, '1.1', TRUE];
          $tests["($constraints)-1.0"] = [$greater_or_equal, '1.0', TRUE];
          $tests["(>={$space}8.x-1.1)-1.0"] = [new Constraint(">={$space}8.x-1.1", '8.x'), '1.0', FALSE];

          // Test less than.
          $constraints = "<{$space}8.x-1.1";
          $less = new Constraint($constraints, '8.x');
          $tests["($constraints)-1.1"] = [$less, '1.1', FALSE];
          $tests["($constraints)-1.1"] = [$less, '1.0', TRUE];
          $tests["($constraints)-1.0"] = [new Constraint("<{$space}8.x-1.0", '8.x'), '1.1', FALSE];

          // Test less than or equal.
          $constraints = "<={$space}8.x-1.x";
          $less_or_equal = new Constraint($constraints, '8.x');
          $tests["($constraints)-2.0"] = [$less_or_equal, '2.0', FALSE];
          $tests["($constraints)-1.9"] = [$less_or_equal, '1.9', TRUE];
          $tests["($constraints)-1.1"] = [$less_or_equal, '1.1', TRUE];
          $tests["($constraints)-0.9"] = [$less_or_equal, '0.9', TRUE];

          // Test greater than and less than.
          $constraints = "<{$space}8.x-4.x,{$space}>{$space}8.x-1.x";
          $less_and_greater = new Constraint($constraints, '8.x');
          $tests["($constraints)-4.0"] = [$less_and_greater, '4.0', FALSE];
          $tests["($constraints)-3.9"] = [$less_and_greater, '3.9', TRUE];
          $tests["($constraints)-2.1"] = [$less_and_greater, '2.1', TRUE];
          $tests["($constraints)-1.9"] = [$less_and_greater, '1.9', FALSE];

          // Test greater than or equals and equals minor version. Both of these
          // conditions will pass.
          $constraints = "{$equal_operator}{$space}8.x-2.x,{$space}>= 2.4-alpha2";
          $greater_and_equal_major = new Constraint($constraints, '8.x');
          $tests["($constraints)-8.x-2.4-beta3"] = [$greater_and_equal_major, '2.4-beta3', TRUE];

          // Test greater than  or equals and equals exact version.
          $constraints = "{$equal_operator}{$space}8.x-2.0,$space>= 2.4-alpha2";
          $greater_and_equal_exact = new Constraint($constraints, '8.x');
          $tests["($constraints)-8.x-2.4-beta3"] = [$greater_and_equal_exact, '2.4-beta3', FALSE];

          // Test a nonsensical greater than and less than - no compatible versions.
          $constraints = ">{$space}8.x-4.x,{$space}< 8.x-1.x";
          $less_and_greater = new Constraint($constraints, '8.x');
          $tests["($constraints)-4.0"] = [$less_and_greater, '4.0', FALSE];
          $tests["($constraints)-3.9"] = [$less_and_greater, '3.9', FALSE];
          $tests["($constraints)-2.1"] = [$less_and_greater, '2.1', FALSE];
          $tests["($constraints)-1.9"] = [$less_and_greater, '1.9', FALSE];

          // Test greater than and less than with an incorrect core compatbility.
          $constraints = "<{$space}8.x-4.x,$space>{$space}8.x-1.x";
          $less_and_greater = new Constraint($constraints, '7.x');
          $tests["($constraints)-4.0-7.x"] = [$less_and_greater, '4.0', FALSE];
          $tests["($constraints)-3.9-7.x"] = [$less_and_greater, '3.9', FALSE];
          $tests["($constraints)-2.1-7.x"] = [$less_and_greater, '2.1', FALSE];
          $tests["($constraints)-1.9-7.x"] = [$less_and_greater, '1.9', FALSE];

          // Test 2 equals with 1 that matching and with nonsensical missing a dash.
          $constraints = "{$equal_operator}{$space}8.x2.x,{$space}{$equal_operator}{$space}2.4-beta3";
          $tests["($constraints)-2.4-beta3"] = [new Constraint($constraints, '8.x'), '2.4-beta3', FALSE];

          // Test with a missing dash.
          $constraints = "{$equal_operator}{$space}8.x2";
          $tests["($constraints)-8.x"] = [new Constraint($constraints, '8.x'), '8.x', TRUE];

          // Test multiple equals which will always be false.
          $constraints = "{$equal_operator}{$space}8.x-2.1,{$space}{$equal_operator}{$space}8.x-2.3,\"(>{$space}1.0,$space<={$space}3.2,{$space}{$not_equals_operator}{$space}3.0)-8.x.2.5";
          $equals_x3 = new Constraint($constraints, '8.x');
          $tests["($constraints)-2.1"] = [$equals_x3, '2.1', FALSE];
          $tests["($constraints)-2.2"] = [$equals_x3, '2.2', FALSE];

          // Test with a range and multiple exclusions.
          $constraints = ">{$space}1.0,$space<= 3.2,$space$not_equals_operator{$space}3.0,$space$not_equals_operator{$space}1.5,$space$not_equals_operator{$space}2.7";
          $greater_less_not_exact = new Constraint($constraints, '8.x');
          $tests["($constraints)-1.1"] = [$greater_less_not_exact, '1.1', TRUE];
          $tests["($constraints)-3.1"] = [$greater_less_not_exact, '3.1', TRUE];
          $tests["($constraints)-2.1"] = [$greater_less_not_exact, '2.1', TRUE];
          $tests["($constraints)-3.0"] = [$greater_less_not_exact, '3.0', FALSE];
          $tests["($constraints)-1.5"] = [$greater_less_not_exact, '1.5', FALSE];
          $tests["($constraints)-2.7"] = [$greater_less_not_exact, '2.7', FALSE];
          $tests["($constraints)-3.3"] = [$greater_less_not_exact, '3.3', FALSE];
        }
      }
    }
    return $tests;
  }

  /**
   * @covers ::toArray
   * @group legacy
   * @expectedDeprecation Drupal\Component\Version\Constraint::toArray() only exists to provide a backwards compatibility layer. See https://www.drupal.org/node/2756875
   */
  public function testToArray() {
    $constraint = new Constraint('<{$space}8.x-4.x,$space>{$space}8.x-1.x', '8.x');
    $this->assertSame([
      ['op' => '<', 'version' => '4.x'],
      ['op' => '>', 'version' => '2.x'],
    ], $constraint->toArray());
  }

}

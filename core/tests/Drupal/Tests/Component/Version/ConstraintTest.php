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
   * Original for testIsCompatible.
   */
  private function original_providerIsCompatible() {
    $tests = [];

    $tests['no-dependencies'] = [new Constraint('', '8.x'), '8.1.x', TRUE];

    // Stable version.
    $stable = new Constraint('8.x-1.0', '8.x');
    $tests['(=8.x-1.0)-1.0'] = [$stable, '1.0', TRUE];
    $tests['(=8.x-1.0)-1.1'] = [$stable, '1.1', FALSE];
    $tests['(=8.x-1.0)-0.9'] = [$stable, '0.9', FALSE];

    // Alpha version.
    $alpha = new Constraint('8.x-1.1-alpha12', '8.x');
    $tests['(8.x-1.1-alpha12)-alpha12'] = [$alpha, '1.1-alpha12', TRUE];
    $tests['(8.x-1.1-alpha12)-alpha10'] = [$alpha, '1.1-alpha10', FALSE];
    $tests['(8.x-1.1-alpha12)-beta1'] = [$alpha, '1.1-beta1', FALSE];

    // Beta version.
    $beta = new Constraint('8.x-1.1-beta8', '8.x');
    $tests['(8.x-1.1-beta8)-beta8'] = [$beta, '1.1-beta8', TRUE];
    $tests['(8.x-1.1-beta8)-beta4'] = [$beta, '1.1-beta4', FALSE];

    // RC version.
    $rc = new Constraint('8.x-1.1-rc11', '8.x');
    $tests['(8.x-1.1-rc11)-rc11'] = [$rc, '1.1-rc11', TRUE];
    $tests['(8.x-1.1-rc11)-rc2'] = [$rc, '1.1-rc2', FALSE];

    // Test greater than.
    $greater = new Constraint('>8.x-1.x', '8.x');
    $tests['(>8.x-1.x)-2.0'] = [$greater, '2.0', TRUE];
    $tests['(>8.x-1.x)-1.1'] = [$greater, '1.1', FALSE];
    $tests['(>8.x-1.x)-0.9'] = [$greater, '0.9', FALSE];

    // Test greater than or equal.
    $greater_or_equal = new Constraint('>=8.x-1.0', '8.x');
    $tests['(>=8.x-1.0)-1.1'] = [$greater_or_equal, '1.1', TRUE];
    $tests['(>=8.x-1.0)-1.0'] = [$greater_or_equal, '1.0', TRUE];
    $tests['(>=8.x-1.1)-1.0'] = [new Constraint('>=8.x-1.1', '8.x'), '1.0', FALSE];

    // Test less than.
    $less = new Constraint('<8.x-1.1', '8.x');
    $tests['(<8.x-1.1)-1.1'] = [$less, '1.1', FALSE];
    $tests['(<8.x-1.1)-1.1'] = [$less, '1.0', TRUE];
    $tests['(<8.x-1.0)-1.0'] = [new Constraint('<8.x-1.0', '8.x'), '1.1', FALSE];

    // Test less than or equal.
    $less_or_equal = new Constraint('<= 8.x-1.x', '8.x');
    $tests['(<= 8.x-1.x)-2.0'] = [$less_or_equal, '2.0', FALSE];
    $tests['(<= 8.x-1.x)-1.9'] = [$less_or_equal, '1.9', TRUE];
    $tests['(<= 8.x-1.x)-1.1'] = [$less_or_equal, '1.1', TRUE];
    $tests['(<= 8.x-1.x)-0.9'] = [$less_or_equal, '0.9', TRUE];

    // Test greater than and less than.
    $less_and_greater = new Constraint('<8.x-4.x,>8.x-1.x', '8.x');
    $tests['(<8.x-4.x,>8.x-1.x)-4.0'] = [$less_and_greater, '4.0', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-3.9'] = [$less_and_greater, '3.9', TRUE];
    $tests['(<8.x-4.x,>8.x-1.x)-2.1'] = [$less_and_greater, '2.1', TRUE];
    $tests['(<8.x-4.x,>8.x-1.x)-1.9'] = [$less_and_greater, '1.9', FALSE];

    // Test a nonsensical greater than and less than - no compatible versions.
    $less_and_greater = new Constraint('>8.x-4.x,<8.x-1.x', '8.x');
    $tests['(<8.x-4.x,>8.x-1.x)-4.0'] = [$less_and_greater, '4.0', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-3.9'] = [$less_and_greater, '3.9', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-2.1'] = [$less_and_greater, '2.1', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-1.9'] = [$less_and_greater, '1.9', FALSE];

    return $tests;
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
          // Test greater than and less than with an incorrect core
          // compatibility.
          $constraint = "<{$space}8.x-4.x,{$space}>{$space}8.x-1.x";
          $tests += $this->createTestsForVersions($constraint, ['4.0', '3.9', '2.1', '1.9'], FALSE, '7.x');
          $tests += $this->createTestsForVersions($constraint, ['4.0', '3.9', '2.1', '1.9'], FALSE, '9.x');

          // Test that core compatibility works for different core versions.
          foreach (['8.x', '9.x', '10.x'] as $core_compatibility) {

            // Stable version.
            $constraint = "{$equal_operator}{$space}$core_compatibility-1.0";
            $tests += $this->createTestsForVersions($constraint, ['1.0'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.1', '0.9'], FALSE, $core_compatibility);

            // Alpha version.
            $constraint = "{$equal_operator}{$space}$core_compatibility-1.1-alpha12";
            $tests += $this->createTestsForVersions($constraint, ['1.1-alpha12'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.1-alpha10', '1.1-beta1'], FALSE, $core_compatibility);

            // Beta version.
            $constraint = "{$equal_operator}{$space}$core_compatibility-1.1-beta8";
            $tests += $this->createTestsForVersions($constraint, ['1.1-beta8'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.1-beta4'], FALSE, $core_compatibility);

            // RC version.
            $constraint = "{$equal_operator}{$space}$core_compatibility-1.1-rc11";
            $tests += $this->createTestsForVersions($constraint, ['1.1-rc11'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.1-rc2'], FALSE, $core_compatibility);

            // Test greater than.
            $constraint = ">{$space}$core_compatibility-1.x";
            $tests += $this->createTestsForVersions($constraint, ['2.0'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.1', '0.9'], FALSE, $core_compatibility);

            // Test greater than or equal.
            $tests += $this->createTestsForVersions(">={$space}$core_compatibility-1.0", ['1.1', '1.0'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions(">={$space}$core_compatibility-1.1", ['1.0'], FALSE, $core_compatibility);

            // Test less than.
            $constraint = "<{$space}$core_compatibility-1.1";
            $tests += $this->createTestsForVersions($constraint, ['1.1'], FALSE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.0'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions("<{$space}$core_compatibility-1.0", ['1.1'], FALSE, $core_compatibility);

            // Test less than or equal.
            $constraint = "<={$space}$core_compatibility-1.x";
            $tests += $this->createTestsForVersions($constraint, ['2.0'], FALSE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['1.9', '1.1', '0.9'], TRUE, $core_compatibility);

            // Test greater than and less than.
            $constraint = "<{$space}$core_compatibility-4.x,{$space}>{$space}$core_compatibility-1.x";
            $tests += $this->createTestsForVersions($constraint, ['4.0', '1.9'], FALSE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['3.9', '2.1'], TRUE, $core_compatibility);

            // Test greater than and less than with no core version in constraint.
            $constraint = "<{$space}4.x,{$space}>{$space}1.x";
            $tests += $this->createTestsForVersions($constraint, ['4.0', '1.9'], FALSE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['3.9', '2.1'], TRUE, $core_compatibility);

            // Test greater than or equals and equals minor version. Both of these
            // conditions will pass.
            $constraint = "{$equal_operator}{$space}$core_compatibility-2.x,{$space}>={$space}2.4-alpha2";
            $tests += $this->createTestsForVersions($constraint, ['2.4-beta3'], TRUE, $core_compatibility);

            // Test greater than or equals and equals exact version.
            $constraint = "{$equal_operator}{$space}$core_compatibility-2.0,$space>={$space}2.4-alpha2";
            $tests += $this->createTestsForVersions($constraint, ['2.4-beta3'], FALSE, $core_compatibility);

            // Test unsatisfiable greater than and less than.
            $constraint = ">{$space}$core_compatibility-4.x,{$space}<{$space}$core_compatibility-1.x";
            $tests += $this->createTestsForVersions($constraint, ['4.0', '3.9', '2.1', '1.9'], FALSE, $core_compatibility);

            // Test 2 equals with 1 that matching and with nonsensical missing a
            // dash.
            $constraint = "{$equal_operator}{$space}8.x2.x,{$space}{$equal_operator}{$space}2.4-beta3";
            $tests += $this->createTestsForVersions($constraint, ['2.4-beta3'], FALSE, $core_compatibility);

            // Test with a missing dash.
            $constraint = "{$equal_operator}{$space}8.x2";
            $tests += $this->createTestsForVersions($constraint, ['8.x'], TRUE, $core_compatibility);

            // Test unsatisfiable multiple equals.
            $constraint = "{$equal_operator}{$space}$core_compatibility-2.1,{$space}{$equal_operator}{$space}$core_compatibility-2.3,\"(>{$space}1.0,$space<={$space}3.2,{$space}{$not_equals_operator}{$space}3.0)-8.x.2.5";
            $tests += $this->createTestsForVersions($constraint, ['2.1', '2.2', '2.3'], FALSE, $core_compatibility);

            // Test with a range and multiple exclusions.
            $constraint = ">{$space}1.0,$space<={$space}3.2,$space$not_equals_operator{$space}3.0,$space$not_equals_operator{$space}1.5,$space$not_equals_operator{$space}2.7";
            $tests += $this->createTestsForVersions($constraint, ['1.1', '3.1', '2.1'], TRUE, $core_compatibility);
            $tests += $this->createTestsForVersions($constraint, ['3.0', '1.5', '2.7', '3.3'], FALSE, $core_compatibility);
          }
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
    $constraint = new Constraint('<8.x-4.x,>8.x-1.x', '8.x');
    $this->assertSame([
      ['op' => '<', 'version' => '4.x'],
      ['op' => '>', 'version' => '2.x'],
    ], $constraint->toArray());
  }

  /**
   * Create test cases for constraints and versions.
   *
   * @param string $constraint_string
   *   The constraint string to test.
   * @param array $versions
   *   The versions.
   * @param bool $expected_result
   *   The expect result for all versions.
   * @param string $core_compatibility
   *   The core compatibility.
   *
   * @return array
   *   The test cases.
   */
  private function createTestsForVersions($constraint_string, array $versions, $expected_result, $core_compatibility = '8.x') {
    $constraint = new Constraint($constraint_string, $core_compatibility);
    $tests = [];
    foreach ($versions  as $version) {
      $tests["$core_compatibility-($constraint_string)-$version"] = [$constraint, $version, $expected_result];
    }
    return $tests;
  }


  /**
   * Just a temporary method to make the patch easier to review.
   *
   * This proves all the original test cases are within the new cases.
   */
  public function testTempToProveAllOldCasesCovered() {
    $original_tests = array_map( 'serialize', $this->original_providerIsCompatible());
    $new_tests = array_map('serialize', $this->providerIsCompatible());
    foreach ($original_tests as $key => $original_test) {
      $this->assertTrue(in_array($original_test, $new_tests, TRUE), "Test case covered: $key");
    }
  }

}

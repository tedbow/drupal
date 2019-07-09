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
          $constraint = "{$equal_operator}{$space}8.x-1.0";
          $tests += $this->createTestsForVersions($constraint, ['1.0'], TRUE);
          $tests += $this->createTestsForVersions($constraint, ['1.1', '0.9'], FALSE);

          // Alpha version.
          $constraint = "{$equal_operator}{$space}8.x-1.1-alpha12";
          $tests += $this->createTestsForVersions($constraint, ['1.1-alpha12'], TRUE);
          $tests += $this->createTestsForVersions($constraint, ['1.1-alpha10', '1.1-beta1'], FALSE);

          // Beta version.
          $constraint = "{$equal_operator}{$space}8.x-1.1-beta8";
          $tests += $this->createTestsForVersions($constraint, ['1.1-beta8'], TRUE);
          $tests += $this->createTestsForVersions($constraint, ['1.1-beta4'], FALSE);

          // RC version.
          $constraint = "{$equal_operator}{$space}8.x-1.1-rc11";
          $tests += $this->createTestsForVersions($constraint, ['1.1-rc11'], TRUE);
          $tests += $this->createTestsForVersions($constraint, ['1.1-rc2'], FALSE);

          // Test greater than.
          $constraint = ">{$space}8.x-1.x";
          $tests += $this->createTestsForVersions($constraint, ['2.0'], TRUE);
          $tests += $this->createTestsForVersions($constraint, ['1.1', '0.9'], FALSE);

          // Test greater than or equal.
          $tests += $this->createTestsForVersions(">={$space}8.x-1.0", ['1.1', '1.0'], TRUE);
          $tests += $this->createTestsForVersions(">={$space}8.x-1.1", ['1.0'], FALSE);

          // Test less than.
          $constraint = "<{$space}8.x-1.1";
          $tests += $this->createTestsForVersions($constraint, ['1.1'], FALSE);
          $tests += $this->createTestsForVersions($constraint, ['1.0'], TRUE);
          $tests += $this->createTestsForVersions("<{$space}8.x-1.0", ['1.1'], FALSE);

          // Test less than or equal.
          $constraint = "<={$space}8.x-1.x";
          $tests += $this->createTestsForVersions($constraint, ['2.0'], FALSE);
          $tests += $this->createTestsForVersions($constraint, ['1.9', '1.1', '0.9'], TRUE);

          // Test greater than and less than.
          $constraint = "<{$space}8.x-4.x,{$space}>{$space}8.x-1.x";
          $tests += $this->createTestsForVersions($constraint, ['4.0', '1.9'], FALSE);
          $tests += $this->createTestsForVersions($constraint, ['3.9', '2.1'], TRUE);

          // Test greater than or equals and equals minor version. Both of these
          // conditions will pass.
          $constraint = "{$equal_operator}{$space}8.x-2.x,{$space}>={$space}2.4-alpha2";
          $tests += $this->createTestsForVersions($constraint, ['2.4-beta3'], TRUE);

          // Test greater than or equals and equals exact version.
          $constraint = "{$equal_operator}{$space}8.x-2.0,$space>={$space}2.4-alpha2";
          $tests += $this->createTestsForVersions($constraint, ['2.4-beta3'], FALSE);

          // Test unsatisfiable greater than and less than.
          $constraint = ">{$space}8.x-4.x,{$space}<{$space}8.x-1.x";
          $tests += $this->createTestsForVersions($constraint, ['4.0', '3.9', '2.1', '1.9'], FALSE);

          // Test greater than and less than with an incorrect core
          // compatibility.
          $tests += $this->createTestsForVersions($constraint, ['4.0', '3.9', '2.1', '1.9'], FALSE, '7.x');

          // Test 2 equals with 1 that matching and with nonsensical missing a
          // dash.
          $constraint = "{$equal_operator}{$space}8.x2.x,{$space}{$equal_operator}{$space}2.4-beta3";
          $tests += $this->createTestsForVersions($constraint, ['2.4-beta3'], FALSE);

          // Test with a missing dash.
          $constraint = "{$equal_operator}{$space}8.x2";
          $tests += $this->createTestsForVersions($constraint, ['8.x'], TRUE);

          // Test unsatisfiable multiple equals.
          $constraint = "{$equal_operator}{$space}8.x-2.1,{$space}{$equal_operator}{$space}8.x-2.3,\"(>{$space}1.0,$space<={$space}3.2,{$space}{$not_equals_operator}{$space}3.0)-8.x.2.5";
          $tests += $this->createTestsForVersions($constraint, ['2.1', '2.2', '2.3'], FALSE);

          // Test with a range and multiple exclusions.
          $constraint = ">{$space}1.0,$space<={$space}3.2,$space$not_equals_operator{$space}3.0,$space$not_equals_operator{$space}1.5,$space$not_equals_operator{$space}2.7";
          $tests += $this->createTestsForVersions($constraint, ['1.1', '3.1', '2.1'], TRUE);
          $tests += $this->createTestsForVersions($constraint, ['3.0','1.5', '2.7', '3.3'], FALSE);
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

}

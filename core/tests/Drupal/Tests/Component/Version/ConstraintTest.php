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
  public function testIsCompatible(array $version_info, $current_version, $result) {
    $constraint = new Constraint($version_info[0], $version_info[1]);
    $this->assertSame($result, $constraint->isCompatible($current_version));
  }

  /**
   * Provider for testIsCompatible.
   */
  public function providerIsCompatible() {
    $tests = [];

    $tests['no-dependencies'] = [['', '8.x'], '8.1.x', TRUE];

    foreach (['', '=', '=='] as $equal_operator) {
      // Stable version.
      $stable = ["{$equal_operator}8.x-1.0", '8.x'];
      $tests["$equal_operator(=8.x-1.0)-1.0"] = [$stable, '1.0', TRUE];
      $tests["$equal_operator(=8.x-1.0)-1.1"] = [$stable, '1.1', FALSE];
      $tests["$equal_operator(=8.x-1.0)-0.9"] = [$stable, '0.9', FALSE];
    }


    // Alpha version.
    $alpha = ['8.x-1.1-alpha12', '8.x'];
    $tests['(8.x-1.1-alpha12)-alpha12'] = [$alpha, '1.1-alpha12', TRUE];
    $tests['(8.x-1.1-alpha12)-alpha10'] = [$alpha, '1.1-alpha10', FALSE];
    $tests['(8.x-1.1-alpha12)-beta1'] = [$alpha, '1.1-beta1', FALSE];

    // Beta version.
    $beta = ['8.x-1.1-beta8', '8.x'];
    $tests['(8.x-1.1-beta8)-beta8'] = [$beta, '1.1-beta8', TRUE];
    $tests['(8.x-1.1-beta8)-beta4'] = [$beta, '1.1-beta4', FALSE];

    // RC version.
    $rc = ['8.x-1.1-rc11', '8.x'];
    $tests['(8.x-1.1-rc11)-rc11'] = [$rc, '1.1-rc11', TRUE];
    $tests['(8.x-1.1-rc11)-rc2'] = [$rc, '1.1-rc2', FALSE];

    // Test greater than.
    $greater = ['> 8.x-1.x', '8.x'];
    $tests['(>8.x-1.x)-2.0'] = [$greater, '2.0', TRUE];
    $tests['(>8.x-1.x)-1.1'] = [$greater, '1.1', FALSE];
    $tests['(>8.x-1.x)-0.9'] = [$greater, '0.9', FALSE];

    // Test greater than or equal.
    $greater_or_equal = ['>= 8.x-1.0', '8.x'];
    $tests['(>=8.x-1.0)-1.1'] = [$greater_or_equal, '1.1', TRUE];
    $tests['(>=8.x-1.0)-1.0'] = [$greater_or_equal, '1.0', TRUE];
    $tests['(>=8.x-1.1)-1.0'] = [['>=8.x-1.1', '8.x'], '1.0', FALSE];

    // Test less than.
    $less = ['< 8.x-1.1', '8.x'];
    $tests['(<8.x-1.1)-1.1'] = [$less, '1.1', FALSE];
    $tests['(<8.x-1.1)-1.1'] = [$less, '1.0', TRUE];
    $tests['(<8.x-1.0)-1.0'] = [['<8.x-1.0', '8.x'], '1.1', FALSE];

    // Test less than or equal.
    $less_or_equal = ['<= 8.x-1.x', '8.x'];
    $tests['(<= 8.x-1.x)-2.0'] = [$less_or_equal, '2.0', FALSE];
    $tests['(<= 8.x-1.x)-1.9'] = [$less_or_equal, '1.9', TRUE];
    $tests['(<= 8.x-1.x)-1.1'] = [$less_or_equal, '1.1', TRUE];
    $tests['(<= 8.x-1.x)-0.9'] = [$less_or_equal, '0.9', TRUE];

    // Test greater than and less than.
    $less_and_greater = ['< 8.x-4.x, > 8.x-1.x', '8.x'];
    $tests['(<8.x-4.x,>8.x-1.x)-4.0'] = [$less_and_greater, '4.0', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-3.9'] = [$less_and_greater, '3.9', TRUE];
    $tests['(<8.x-4.x,>8.x-1.x)-2.1'] = [$less_and_greater, '2.1', TRUE];
    $tests['(<8.x-4.x,>8.x-1.x)-1.9'] = [$less_and_greater, '1.9', FALSE];

    // Test greater than or equals and equals minor version. Both of these
    // conditions will pass.
    $greater_and_equal_major = ['= 8.x-2.x, >= 2.4-alpha2', '8.x'];
    $tests['(=8.x-2.x, >=2.4-alpha2)-8.x-2.4-beta3'] = [$greater_and_equal_major, '2.4-beta3', TRUE];

    // Test greater than  or equals and equals exact version.
    $greater_and_equal_exact = ['= 8.x-2.0, >= 2.4-alpha2', '8.x'];
    $tests['(=8.x-2.0, >=2.4-alpha2)-8.x-2.4-beta3'] = [$greater_and_equal_exact, '2.4-beta3', FALSE];

    // Test a nonsensical greater than and less than - no compatible versions.
    $less_and_greater = ['> 8.x-4.x, < 8.x-1.x', '8.x'];
    $tests['(<8.x-4.x,>8.x-1.x)-4.0'] = [$less_and_greater, '4.0', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-3.9'] = [$less_and_greater, '3.9', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-2.1'] = [$less_and_greater, '2.1', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-1.9'] = [$less_and_greater, '1.9', FALSE];

    // Test greater than and less than with an incorrect core compatbility.
    $less_and_greater = ['< 8.x-4.x, > 8.x-1.x', '7.x'];
    $tests['(<8.x-4.x,>8.x-1.x)-4.0-7.x'] = [$less_and_greater, '4.0', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-3.9-7.x'] = [$less_and_greater, '3.9', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-2.1-7.x'] = [$less_and_greater, '2.1', FALSE];
    $tests['(<8.x-4.x,>8.x-1.x)-1.9-7.x'] = [$less_and_greater, '1.9', FALSE];

    // Test 2 equals with 1 that matching and with nonsensical missing a dash.
    $tests['(=8.x2.x,=2.4-beta3)-2.4-beta3'] = [['= 8.x2.x, = 2.4-beta3', '8.x'], '2.4-beta3', FALSE];

    // Test with a missing dash.
    $tests['(= 8.x2)-8.x'] = [['= 8.x2', '8.x'], '8.x', TRUE];

    // Test multiple equals which will always be false.
    $equals_x3 = ['= 8.x-2.1, = 8.x-2.3, 8.x.2.5', '8.x'];
    $tests['(=8.x-2.1,=8.x-2.3,8.x.2.5)-2.1'] = [$equals_x3, '2.1', FALSE];
    $tests['(=8.x-2.1,=8.x-2.3,8.x.2.5)-2.1'] = [$equals_x3, '2.2', FALSE];

    // Test with a range and multiple exclusions.
    $greater_less_not_exact = ['> 1.0, <= 3.2, != 3.0, != 1.5, != 2.7', '8.x'];
    $tests['(>1.0, <=3.2, !=3.0)-1.1'] = [$greater_less_not_exact, '1.1', TRUE];
    $tests['(>1.0, <=3.2, !=3.0)-3.1'] = [$greater_less_not_exact, '3.1', TRUE];
    $tests['(>1.0, <=3.2, !=3.0)-2.1'] = [$greater_less_not_exact, '2.1', TRUE];
    $tests['(>1.0, <=3.2, !=3.0)-3.0'] = [$greater_less_not_exact, '3.0', FALSE];
    $tests['(>1.0, <=3.2, !=3.0)-1.5'] = [$greater_less_not_exact, '1.5', FALSE];
    $tests['(>1.0, <=3.2, !=3.0)-2.7'] = [$greater_less_not_exact, '2.7', FALSE];
    $tests['(>1.0, <=3.2, !=3.0)-3.3'] = [$greater_less_not_exact, '3.3', FALSE];

    $original_tests = $tests;
    foreach ($tests as $key => $test) {
      if (strpos($test[0][0], ' ') !== FALSE) {
        $tests["$key-no-space"] = $test;
        $tests["$key-no-space"][0][0] = str_replace(' ', '', $test[0][0]);

        $tests["$key-extra-space"] = $test;
        $tests["$key-extra-space"][0][0] = str_replace(' ', '     ', $test[0][0]);
      }
    }
    foreach ($original_tests as $key => $original_test) {
      if (strpos($original_test[0][0], '!=') !== FALSE) {
        $new_key = str_replace('!=', '<>', $key);
        $tests[$new_key] = $original_test;
        $tests[$new_key][0][0] = str_replace('!=', '<>', $original_test[0][0]);
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

}

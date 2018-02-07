<?php

namespace Drupal\KernelTests\Core\File;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Tests filename munging and unmunging.
 *
 *
 * Checks performed, relying on 2 <= strlen('foo') <= 5.
 *
 * - testMunging()
 *   - (name).foo.txt -> (name).foo_.txt when allows_insecure_uploads === 0
 * - testMungeBullByte()
 *   - (name).foo\0.txt -> (name).foo_.txt regardless of allows_insecure_uploads
 * - testMungeIgnoreInsecure()
 *   - (name).foo.txt unmodified when allows_insecure_uploads === 1
 * - testMungeIgnoreWhitelisted()
 *   - (name).FOO.txt -> (name).FOO when white-listing 'foo'.
 *   - (name).foo.txt -> (name).foo.txt when white-listing 'FOO'.
 * - testMungeBlacklisted()
 *   - (name).php.txt -> (name).php_.txt even when white-listing 'php txt'
 * - testUnMunge()
 *   - (name).foo.txt -> (unchecked) -> (name).foo.txt after un-munging
 *
 * @group File
 */
class NameMungingTest extends FileTestBase {

  /**
   * An extension to be used as forbidden during munge operations.
   *
   * @var string
   */
  protected $badExtension;

  /**
   * The name of a file with a bad extension, after munging.
   *
   * @var string
   */
  protected $name;

  /**
   * The name of a file with an upper-cased bad extension, after munging.
   *
   * @var string
   */
  protected $nameWithUcExt;

  protected function setUp() {
    parent::setUp();
    $this->badExtension = 'foo';
    $this->name = $this->randomMachineName() . '.' . $this->badExtension . '.txt';
    $this->nameWithUcExt = $this->randomMachineName() . '.' . strtoupper($this->badExtension) . '.txt';
  }

  /**
   * Create a file and munge/unmunge the name.
   */
  public function testMunging() {
    // Disable insecure uploads.
    $this->config('system.file')->set('allow_insecure_uploads', 0)->save();
    $munged_name = file_munge_filename($this->name, '', TRUE);
    $messages = drupal_get_messages();
    $this->assertTrue(in_array(strtr('For security reasons, your upload has been renamed to <em class="placeholder">%filename</em>.', [
      '%filename' => $munged_name,
    ]), $messages['status']), 'Alert properly set when a file is renamed.');
    $this->assertNotEquals($munged_name, $this->name, new FormattableMarkup('The new filename (%munged) has been modified from the original (%original)', [
      '%munged' => $munged_name,
      '%original' => $this->name,
    ]));
  }

  /**
   * Tests munging with a null byte in the filename.
   */
  public function testMungeNullByte() {
    $prefix = $this->randomMachineName();
    $filename = $prefix . '.' . $this->badExtension . "\0.txt";
    $this->assertEquals(file_munge_filename($filename, ''), $prefix . '.' . $this->badExtension . '_.txt', 'A filename with a null byte is correctly munged to remove the null byte.');
  }

  /**
   * If the system.file.allow_insecure_uploads setting evaluates to true, the file should
   * come out untouched, no matter how evil the filename.
   */
  public function testMungeIgnoreInsecure() {
    $this->config('system.file')->set('allow_insecure_uploads', 1)->save();
    $munged_name = file_munge_filename($this->name, '');
    $this->assertSame($munged_name, $this->name, new FormattableMarkup('The original filename (%original) matches the munged filename (%munged) when insecure uploads are enabled.', [
      '%munged' => $munged_name,
      '%original' => $this->name,
    ]));
  }

  /**
   * White listed extensions are ignored by file_munge_filename().
   */
  public function testMungeIgnoreWhitelisted() {
    // Declare our extension as whitelisted. The declared extensions should
    // be case insensitive so test using one with a different case.
    $munged_name = file_munge_filename($this->nameWithUcExt, $this->badExtension);
    $this->assertSame($munged_name, $this->nameWithUcExt, new FormattableMarkup('The new filename (%munged) matches the original (%original) once the extension has been whitelisted.', [
      '%munged' => $munged_name,
      '%original' => $this->nameWithUcExt,
    ]));
    // The allowed extensions should also be normalized.
    $munged_name = file_munge_filename($this->name, strtoupper($this->badExtension));
    $this->assertSame($munged_name, $this->name, new FormattableMarkup('The new filename (%munged) matches the original (%original) also when the whitelisted extension is in uppercase.', [
      '%munged' => $munged_name,
      '%original' => $this->name,
    ]));
  }

  /**
   * Blacklisted extensions are munged by file_munge_filename().
   */
  public function testMungeBlacklisted() {
    $prefix = $this->randomMachineName();
    $name = "$prefix.php.txt";
    // Put the php extension in the white list, but since it is blacklisted by
    // the unsafe extension list, it should still be munged.
    $munged_name = file_munge_filename($name, 'php txt');
    $this->assertSame($munged_name, "$prefix.php_.txt", new FormattableMarkup('The filename (%munged) has been modified from the original (%original) if the whitelisted extension is also on the blacklist.', [
      '%munged' => $munged_name,
      '%original' => $name,
    ]));
  }

  /**
   * Ensure that unmunge gets your name back.
   */
  public function testUnMunge() {
    $munged_name = file_munge_filename($this->name, '', FALSE);
    $unmunged_name = file_unmunge_filename($munged_name);
    $this->assertSame($unmunged_name, $this->name, new FormattableMarkup('The unmunged (%unmunged) filename matches the original (%original)', [
      '%unmunged' => $unmunged_name,
      '%original' => $this->name,
    ]));
  }

}

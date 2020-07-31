<?php

namespace Drupal\automatic_updates\ReadinessChecker;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for filesystem checkers.
 */
abstract class Filesystem implements ReadinessCheckerInterface {
  use StringTranslationTrait;

  /**
   * The root file path.
   *
   * @var string
   */
  protected $rootPath;

  /**
   * The vendor file path.
   *
   * @var string
   */
  protected $vendorPath;

  /**
   * Filesystem constructor.
   *
   * @param string $app_root
   *   The app root.
   */
  public function __construct($app_root) {
    $this->rootPath = (string) $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    if (!file_exists($this->getRootPath() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['core', 'core.api.php']))) {
      return [$this->t('The web root could not be located.')];
    }

    return $this->doCheck();
  }

  /**
   * Perform checks.
   *
   * @return array
   *   An array of translatable strings if any checks fail.
   */
  abstract protected function doCheck();

  /**
   * Get the root file path.
   *
   * @return string
   *   The root file path.
   */
  protected function getRootPath() {
    if (!$this->rootPath) {
      $this->rootPath = (string) \Drupal::root();
    }
    return $this->rootPath;
  }

  /**
   * Get the vendor file path.
   *
   * @return string
   *   The vendor file path.
   */
  protected function getVendorPath() {
    if (!$this->vendorPath) {
      $this->vendorPath = $this->getRootPath() . DIRECTORY_SEPARATOR . 'vendor';
    }
    return $this->vendorPath;
  }

  /**
   * Determine if the root and vendor file system are the same logical disk.
   *
   * @param string $root
   *   Root file path.
   * @param string $vendor
   *   Vendor file path.
   *
   * @return bool
   *   TRUE if same file system, FALSE otherwise.
   */
  protected function areSameLogicalDisk($root, $vendor) {
    $root_statistics = stat($root);
    $vendor_statistics = stat($vendor);
    return $root_statistics && $vendor_statistics && $root_statistics['dev'] === $vendor_statistics['dev'];
  }

}

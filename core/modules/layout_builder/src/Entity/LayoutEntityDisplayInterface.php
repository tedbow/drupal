<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\layout_builder\SectionListInterface;

/**
 * Provides an interface for entity displays that have layout.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 */
interface LayoutEntityDisplayInterface extends EntityDisplayInterface, SectionListInterface {

  /**
   * Determines if the display allows custom overrides.
   *
   * @return bool
   *   TRUE if custom overrides are allowed, FALSE otherwise.
   */
  public function isOverridable();

  /**
   * Sets the display to allow or disallow overrides.
   *
   * @param bool $overridable
   *   TRUE if the display should allow overrides, FALSE otherwise.
   *
   * @return $this
   */
  public function setOverridable($overridable = TRUE);

  /**
   * Determines if Layout Builder is enabled for this display.
   *
   * @return bool
   *   TRUE if Layout Builder is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Enables the Layout Builder for this display.
   *
   * @param bool $enabled
   *   (optional) TRUE if Layout Builder should be enabled, FALSE otherwise.
   * @param bool $force_sync
   *   (optional) TRUE if components should be synced regardless of status,
   *   FALSE otherwise. Defaults to FALSE.
   *
   * @return $this
   */
  public function setEnabled($enabled = TRUE, $force_sync = FALSE);

}

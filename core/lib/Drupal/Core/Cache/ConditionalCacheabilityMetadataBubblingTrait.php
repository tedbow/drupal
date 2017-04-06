<?php

namespace Drupal\Core\Cache;

/**
 * Provides bubble function to apply cacheable dependency to render context.
 *
 * This trait should be used with great care. It should only be used by classes
 * that may be used both inside and outside of a render context.
 * For example:
 * - Generating URLs for CLI vs for HTTP responses.
 * - Serializing/normalizing data for scripts vs for HTTP responses.
 */
trait ConditionalCacheabilityMetadataBubblingTrait {

  /**
   * Bubbles cacheability metadata to the current render context.
   *
   * This method does not bubble attachments.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $object
   *   A cacheable dependency object.
   */
  protected function bubble(CacheableDependencyInterface $object) {
    if ($this->renderer->hasRenderContext()) {
      $build = [];
      CacheableMetadata::createFromObject($object)->applyTo($build);
      $this->renderer->render($build);
    }
  }

}

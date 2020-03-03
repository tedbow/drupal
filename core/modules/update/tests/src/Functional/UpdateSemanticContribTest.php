<?php

namespace Drupal\Tests\update\Functional;

/**
 * Tests the Update Manager module with a contrib module with semantic versions.
 *
 * @group update
 */
class UpdateSemanticContribTest extends UpdateSemanticTestBase {

  /**
   * {@inheritdoc}
   */
  protected $updateTableLocator = 'table.update:nth-of-type(2)';

  /**
   * {@inheritdoc}
   */
  protected $updateProject = 'semantic_test';

  /**
   * {@inheritdoc}
   */
  protected $projectTitle = 'Semantic Test';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['semantic_test'];

  /**
   * {@inheritdoc}
   */
  protected function standardTests() {
  }
  
}

<?php

namespace Drupal\Tests\layout_builder\Functional\Update\Translatability;

/**
 * A test case that updates both bundles' fields.
 *
 * @group layout_builder
 * @group legacy
 */
class LayoutFieldTranslateUpdateCase1 extends MakeLayoutUntranslatableUpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $layoutBuilderTestCases = [
    'article' => [
      'has_translation' => TRUE,
      'has_layout' => FALSE,
      'nid' => 1,
      'vid' => 2,
      'title' => 'Test Article - Spanish title',
    ],
    'page' => [
      'has_translation' => FALSE,
      'has_layout' => TRUE,
      'nid' => 4,
      'vid' => 5,
      'title' => 'Page Test - Spanish title',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected $expectedBundleUpdates = [
    'article' => TRUE,
    'page' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  protected $expectedFieldStorageUpdate = TRUE;

}

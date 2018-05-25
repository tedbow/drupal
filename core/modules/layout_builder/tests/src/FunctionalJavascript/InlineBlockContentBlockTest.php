<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests that the inline block feature works correctly.
 *
 * @group layout_builder
 */
class InlineBlockContentBlockTest extends InlineBlockTestBase {

  use ContextualLinkClickTrait;

  /**
   * {@inheritdoc}
   */
  public static $inlineBlockCssLocator = '.block-inline-block-contentbasic';

  /**
   * {@inheritdoc}
   */
  public static $blockEntityType = 'block_content';


  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function createBlockBundle() {
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

}

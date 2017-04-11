<?php

namespace Drupal\Tests\text\Kernel\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\text\Normalizer\TextItemBaseNormalizer
 * @group text
 */
class TextItemBaseNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test', 'serialization', 'text', 'field', 'user', 'filter', 'filter_test'];

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The text format ID of the fallback format.
   *
   * In a non-unit test, this would be read from the 'filter.settings' config,
   * from the 'fallback_format' key.
   *
   * @var string
   */
  protected static $fallbackFormatId = 'plain_text';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->serializer = \Drupal::service('serializer');

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig('filter');

    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_text',
      'type' => 'text',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_text',
      'bundle' => 'entity_test',
    ])->save();

    FilterFormat::create([
      'format' => 'my_text_format',
      'name' => 'My text format',
      'filters' => [
        'filter_autop' => [
          'module' => 'filter',
          'status' => TRUE,
        ],
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<strong>',
          ],
        ],
        // Include this test filter because it bubbles cache tags.
        'filter_test_cache_tags' => [
          'status' => TRUE,
        ],
        // Include this test filter because it bubbles cache contexts.
        'filter_test_cache_contexts' => [
          'status' => TRUE,
        ],
      ],
    ])->save();
  }

  /**
   * @covers ::normalize
   *
   * @dataProvider testNormalizeProvider
   */
  public function testNormalize($text_item, array $expected, CacheableMetadata $extra_cacheability, array $filter_config_update = [], $updated_processed = '') {
    $original_entity = EntityTest::create(['field_text' => $text_item]);
    $original_entity->save();
    $text_format = FilterFormat::load(empty($text_item['format']) ? static::$fallbackFormatId : $text_item['format']);

    $entity = clone $original_entity;
    $cacheable_metadata = new CacheableMetadata();
    $data = $this->serializer->normalize($entity, 'json', ['cacheability' => $cacheable_metadata]);

    $expected_cacheability = new CacheableMetadata();
    $expected_cacheability->setCacheTags($text_format->getCacheTags());
    $contexts = $this->container->getParameter('renderer.config')['required_cache_contexts'];
    $expected_cacheability->setCacheContexts($contexts);
    // Merge the CacheableMetadata that is specific to this test.
    $expected_cacheability = $expected_cacheability->merge($extra_cacheability);

    $this->assertEquals($expected_cacheability, $cacheable_metadata);
    $this->assertEquals($expected, $data['field_text'][0]);

    if ($filter_config_update) {
      // Update format to see if normalization changes.
      foreach ($filter_config_update as $instance_id => $update) {
        $text_format->setFilterConfig($instance_id, $update);
      }
      $text_format->save();

      $entity = clone $original_entity;
      $cacheable_metadata = new CacheableMetadata();
      $data = $this->serializer->normalize($entity, 'json', ['cacheability' => $cacheable_metadata]);

      $this->assertEquals($expected_cacheability, $cacheable_metadata);
      $expected['processed'] = $updated_processed;
      $this->assertEquals($expected, $data['field_text'][0]);
    }
  }

  /**
   * Data provider for testNormalize().
   */
  public function testNormalizeProvider() {
    $test_cases['no format specified'] = [
      'text item',
      [
        'value' => 'text item',
        'processed' => "<p>text item</p>\n",
        'format' => NULL,
      ],
      (new CacheableMetadata())
        ->setCacheTags(['config:filter.settings']),
    ];
    $test_cases['my text format'] = [
      [
        'value' => '<strong>This</strong> is <b>important.</b>',
        'format' => 'my_text_format',
      ],
      [
        'value' => "<strong>This</strong> is <b>important.</b>",
        'format' => 'my_text_format',
        'processed' => "<p><strong>This</strong> is important.</p>\n",
      ],
      (new CacheableMetadata())
        // Add the tags for the 'filter_test_cache_tags' filter.
        ->setCacheTags(['foo:bar', 'foo:baz'])
        // Add the contexts for the 'filter_test_cache_contexts' filter.
        ->setCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]),
      [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<strong> <b>',
          ],
        ],
      ],
      "<p><strong>This</strong> is <b>important.</b></p>\n",
    ];
    return $test_cases;
  }

}

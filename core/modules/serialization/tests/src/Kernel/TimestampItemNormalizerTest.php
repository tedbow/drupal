<?php

namespace Drupal\Tests\serialization\Kernel;

use Drupal\Core\Field\Plugin\Field\FieldType\CreatedItem;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\serialization\Normalizer\TimestampItemNormalizer;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Tests that entities can be serialized to supported core formats.
 *
 * @group serialization
 * @coversDefaultClass \Drupal\serialization\Normalizer\TimestampItemNormalizer
 */
class TimestampItemNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['serialization', 'system', 'field', 'entity_test', 'text', 'user'];

  /**
   * The normalizer under test.
   *
   * @var \Drupal\serialization\Normalizer\TimestampItemNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->normalizer = new TimestampItemNormalizer();
    $this->normalizer->setSerializer(\Drupal::service('serializer'));

    $this->installConfig(['field']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installSchema('system', 'sequences');
  }

  /**
   * @covers ::supportsNormalization
   */
  public function testSupportsNormalization() {
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);

    $this->assertTrue($this->normalizer->supportsNormalization($entity->created->first()));

    $entity_ref_item = $this->prophesize(EntityReferenceItem::class);
    $this->assertFalse($this->normalizer->supportsNormalization($entity_ref_item->reveal()));
  }

  /**
   * @covers ::supportsDenormalization
   */
  public function testSupportsDenormalization() {
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $this->assertTrue($this->normalizer->supportsDenormalization($entity->created->first(), TimestampItem::class));

    // CreatedItem extends regular TimestampItem.
    $timestamp_item = $this->prophesize(CreatedItem::class);
    $this->assertTrue($this->normalizer->supportsDenormalization($timestamp_item, TimestampItem::class));

    $entity_ref_item = $this->prophesize(EntityReferenceItem::class);
    $this->assertFalse($this->normalizer->supportsNormalization($entity_ref_item->reveal(), TimestampItem::class));
  }

  /**
   * Tests the normalize function.
   *
   * @covers ::normalize
   */
  public function testNormalize() {
    $expected = ['value' => '2016-11-06T09:02:00+00:00', 'format' => \DateTime::RFC3339];

    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'created' => 1478422920,
    ]);

    $normalized = $this->normalizer->normalize($entity->created->first());
    $this->assertSame($expected, $normalized);
  }

  /**
   * Tests the denormalize function with good data.
   *
   * @covers ::denormalize
   * @dataProvider providerTestDenormalizeValidFormats
   */
  public function testDenormalizeValidFormats($value, $expected) {
    $normalized = ['value' => $value];

    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'created' => $expected,
    ]);

    $context = ['target_instance' => $entity->created->first()];

    $denormalized = $this->normalizer->denormalize($normalized, TimestampItem::class, NULL, $context);
    $this->assertTrue($denormalized instanceof TimestampItem);
  }

  /**
   * Data provider for testDenormalizeValidFormats.
   *
   * @return array
   */
  public function providerTestDenormalizeValidFormats() {
    $expected_stamp = 1478422920;

    $data = [];

    $data['U'] = [$expected_stamp, $expected_stamp];
    $data['RFC3339'] = ['2016-11-06T09:02:00+00:00', $expected_stamp];
    $data['RFC3339 +0100'] = ['2016-11-06T09:02:00+01:00', $expected_stamp - 1 * 3600];
    $data['RFC3339 -0600'] = ['2016-11-06T09:02:00-06:00', $expected_stamp + 6 * 3600];

    $data['ISO8601'] = ['2016-11-06T09:02:00+0000', $expected_stamp];
    $data['ISO8601 +0100'] = ['2016-11-06T09:02:00+0100', $expected_stamp - 1 * 3600];
    $data['ISO8601 -0600'] = ['2016-11-06T09:02:00-0600', $expected_stamp + 6 * 3600];

    return $data;
  }

  /**
   * Tests the denormalize function with bad data.
   *
   * @covers ::denormalize
   */
  public function testDenormalizeException() {
    $this->setExpectedException(UnexpectedValueException::class, 'The specified date "2016/11/06 09:02am GMT" is not in an accepted format: "U" (UNIX timestamp), "Y-m-d\TH:i:sO" (ISO 8601), "Y-m-d\TH:i:sP" (RFC 3339).');

    $entity = EntityTest::create(['name' => $this->randomMachineName()]);

    $context = ['target_instance' => $entity->created->first()];

    $normalized = ['value' => '2016/11/06 09:02am GMT'];
    $this->normalizer->denormalize($normalized, TimestampItem::class, NULL, $context);
  }

}

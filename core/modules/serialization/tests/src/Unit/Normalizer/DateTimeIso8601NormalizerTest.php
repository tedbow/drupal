<?php

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\serialization\Normalizer\DateTimeIso8601Normalizer;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Unit test coverage for the "datetime_iso8601" @DataType.
 *
 * Only tests the "date only" mode of DateTimeIso8601, because everything else
 * is handled by \Drupal\serialization\Normalizer\DateTimeNormalizer, for which
 * we have \Drupal\Tests\serialization\Unit\Normalizer\DateTimeNormalizerTest.
 *
 * @coversDefaultClass \Drupal\serialization\Normalizer\DateTimeIso8601Normalizer
 * @group serialization
 * @see \Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601
 * @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATE
 */
class DateTimeIso8601NormalizerTest extends UnitTestCase {

  /**
   * The tested data type's normalizer.
   *
   * @var \Drupal\serialization\Normalizer\DateTimeIso8601Normalizer
   */
  protected $normalizer;

  /**
   * The tested data type.
   *
   * @var \Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601
   */
  protected $data;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $config_factory = $this->getConfigFactoryStub(
      [
        'system.date' => [
          'timezone.default' => 'Australia/Sydney',
        ],
      ]
    );
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    \Drupal::setContainer($container);

    $this->normalizer = new DateTimeIso8601Normalizer();
    $this->data = $this->prophesize(DateTimeIso8601::class);
  }

  /**
   * @covers ::supportsNormalization
   */
  public function testSupportsNormalization() {
    $this->assertTrue($this->normalizer->supportsNormalization($this->data->reveal()));

    $datetime = $this->prophesize(DateTimeInterface::class);
    $this->assertFalse($this->normalizer->supportsNormalization($datetime->reveal()));

    $integer = $this->prophesize(IntegerData::class);
    $this->assertFalse($this->normalizer->supportsNormalization($integer->reveal()));
  }

  /**
   * @covers ::supportsDenormalization
   */
  public function testSupportsDenormalization() {
    $this->assertTrue($this->normalizer->supportsDenormalization($this->data->reveal(), DateTimeIso8601::class));
  }

  /**
   * @covers ::normalize
   * @dataProvider providerTestNormalize
   */
  public function testNormalize($parent_field_item_class, $datetime_type, $expected_format) {
    $formatted_string = $this->randomMachineName();

    $field_item = $this->prophesize($parent_field_item_class);
    if ($parent_field_item_class === DateTimeItem::class) {
      $field_storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
      $field_storage_definition->getSetting('datetime_type')
        ->willReturn($datetime_type);
      $field_definition = $this->prophesize(FieldDefinitionInterface::class);
      $field_definition->getFieldStorageDefinition()
        ->willReturn($field_storage_definition);
      $field_item->getFieldDefinition()
        ->willReturn($field_definition);
    }
    else {
      $field_item->getFieldDefinition(Argument::any())
        ->shouldNotBeCalled();
    }
    $this->data->getParent()
      ->willReturn($field_item);

    $drupal_date_time = $this->prophesize(DateTimeIso8601NormalizerTestDrupalDateTime::class);
    $drupal_date_time->setTimezone(new \DateTimeZone('Australia/Sydney'))
      ->willReturn($drupal_date_time->reveal());
    $drupal_date_time->format($expected_format)
      ->willReturn($formatted_string);
    $this->data->getDateTime()
      ->willReturn($drupal_date_time->reveal());

    $normalized = $this->normalizer->normalize($this->data->reveal());
    $this->assertSame($formatted_string, $normalized);
  }

  /**
   * Data provider for testNormalize.
   *
   * @return array
   */
  public function providerTestNormalize() {
    return [
      // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATE
      'datetime field, configured to store only date: must be handled by DateTimeIso8601Normalizer' => [
        DateTimeItem::class,
        DateTimeItem::DATETIME_TYPE_DATE,
        // This expected format call proves that normalization is handled by \Drupal\serialization\Normalizer\DateTimeIso8601Normalizer::normalize().
        'Y-m-d',
      ],
      // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATETIME
      'datetime field, configured to store date and time; must be handled by the parent normalizer' => [
        DateTimeItem::class,
        DateTimeItem::DATETIME_TYPE_DATETIME,
        \DateTime::RFC3339,
      ],
      'non-datetime field; must be handled by the parent normalizer' => [
        FieldItemBase::class,
        NULL,
        \DateTime::RFC3339,
      ],

    ];
  }

  /**
   * Tests the denormalize function with good data.
   *
   * @covers ::denormalize
   * @dataProvider providerTestDenormalizeValidFormats
   */
  public function testDenormalizeValidFormats($normalized, $expected) {
    $denormalized = $this->normalizer->denormalize($normalized, DateTimeIso8601::class, NULL, []);
    $this->assertInstanceOf(\DateTime::class, $denormalized);
    $this->assertEquals('UTC', $denormalized->getTimezone()->getName());
    $this->assertEquals('12:00:00', $denormalized->format('H:i:s'));
    $this->assertEquals($expected->format(\DateTime::RFC3339), $denormalized->format(\DateTime::RFC3339));
  }

  /**
   * Data provider for testDenormalizeValidFormats.
   *
   * @return array
   */
  public function providerTestDenormalizeValidFormats() {
    $data = [];
    $data["denormalized dates have the UTC timezone"] = ['2016-11-06', new \DateTimeImmutable('2016-11-06T12:00:00', new \DateTimeZone('UTC'))];
    return $data;
  }

  /**
   * Tests the denormalize function with bad data.
   *
   * @covers ::denormalize
   */
  public function testDenormalizeException() {
    $this->setExpectedException(UnexpectedValueException::class, 'The specified date "2016/11/06" is not in an accepted format: "Y-m-d" (date-only).');

    $normalized = '2016/11/06';

    $this->normalizer->denormalize($normalized, DateTimeIso8601::class, NULL, []);
  }

}

/**
 * Note: Prophecy does not support magic methods. By subclassing and specifying
 * an explicit method, Prophecy works.
 * @see https://github.com/phpspec/prophecy/issues/338
 * @see https://github.com/phpspec/prophecy/issues/34
 * @see https://github.com/phpspec/prophecy/issues/80
 */
class DateTimeIso8601NormalizerTestDrupalDateTime extends DrupalDateTime {

  public function setTimezone(\DateTimeZone $timezone) {
    parent::setTimezone($timezone);
  }

}

<?php

namespace Drupal\Tests\serialization\Kernel;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;
use Drupal\user\Entity\User;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * @coversDefaultClass \Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer
 * @group serialization
 */
class EntityReferenceFieldItemNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['serialization', 'system', 'field', 'entity_test', 'text', 'user'];

  /**
   * The normalizer under test.
   *
   * @var \Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->normalizer = new EntityReferenceFieldItemNormalizer(\Drupal::service('entity.repository'));
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
    $this->assertTrue($this->normalizer->supportsNormalization($entity->user_id->first()));
    $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
  }

  /**
   * @covers ::supportsDenormalization
   */
  public function testSupportsDenormalization() {
    $this->assertTrue($this->normalizer->supportsDenormalization([], EntityReferenceItem::class));
    $this->assertFalse($this->normalizer->supportsDenormalization([], FieldItemInterface::class));
  }

  /**
   * @covers ::normalize
   */
  public function testNormalize() {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $normalized = $this->normalizer->normalize($entity->user_id->first());

    $expected = [
      'target_id' => (int) $user->id(),
      'target_type' => $user->getEntityTypeId(),
      'target_uuid' => $user->uuid(),
      'url' => $user->url(),
    ];
    $this->assertSame($expected, $normalized);
  }

  /**
   * @covers ::normalize
   */
  public function testNormalizeWithNoEntity() {
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
    ]);

    $normalized = $this->normalizer->normalize($entity->user_id->first());

    $expected = [
      'target_id' => 1,
    ];
    $this->assertSame($expected, $normalized);
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeWithTypeAndUuid() {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $data = [
      'target_id' => $user->id(),
      'target_type' => 'user',
      'target_uuid' => $user->uuid(),
    ];

    $this->assertDenormalize($data, $entity->user_id->first());
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeWithUuidWithoutType() {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $data = [
      'target_id' => $user->id(),
      'target_uuid' => $user->uuid(),
    ];

    $this->assertDenormalize($data, $entity->user_id->first());
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeWithUuidWithIncorrectType() {
    $this->setExpectedException(UnexpectedValueException::class, 'The field "user_id" property "target_type" must be set to "user" or omitted.');

    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $data = [
      'target_id' => $user->id(),
      'target_type' => 'wrong_type',
      'target_uuid' => $user->uuid(),
    ];

    $this->assertDenormalize($data, $entity->user_id->first());
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeWithTypeWithIncorrectUuid() {
    $this->setExpectedException(InvalidArgumentException::class, 'No "user" entity found with UUID "unique-but-none-non-existent" for field "0"');

    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $data = [
      'target_id' => $user->id(),
      'target_type' => $user->getEntityTypeId(),
      'target_uuid' => 'unique-but-none-non-existent',
    ];

    $this->assertDenormalize($data, $entity->user_id->first());
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeWithEmtpyUuid() {
    $this->setExpectedException(InvalidArgumentException::class, 'If provided "target_uuid" cannot be empty for field "user".');

    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $data = [
      'target_id' => $user->id(),
      'target_type' => $user->getEntityTypeId(),
      'target_uuid' => '',
    ];

    $this->assertDenormalize($data, $entity->user_id->first());
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeWithId() {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => [
        'target_id' => $user->id(),
      ],
    ]);

    $data = [
      'target_id' => $user->id(),
    ];

    $this->assertDenormalize($data, $entity->user_id->first());
  }

  /**
   * Asserts denormalization process is correct for given data.
   *
   * @param array $data
   *   The data to denormalize.
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   */
  protected function assertDenormalize(array $data, $field_item) {
    $context = ['target_instance' => $field_item];
    $denormalized = $this->normalizer->denormalize($data, EntityReferenceItem::class, 'json', $context);
    $this->assertSame($context['target_instance'], $denormalized);
  }

}

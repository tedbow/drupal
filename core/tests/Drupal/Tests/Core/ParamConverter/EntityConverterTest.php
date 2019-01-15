<?php

namespace Drupal\Tests\Core\ParamConverter;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\Core\ParamConverter\EntityConverter
 * @group ParamConverter
 * @group Entity
 */
class EntityConverterTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $contextRepository;

  /**
   * The mocked entities repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityRepository;

  /**
   * The tested entity converter.
   *
   * @var \Drupal\Core\ParamConverter\EntityConverter
   */
  protected $entityConverter;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->contextRepository = $this->createMock(ContextRepositoryInterface::class);
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);

    $this->entityConverter = new EntityConverter($this->entityTypeManager, $this->contextRepository, $this->entityRepository);
  }

  /**
   * Sets up mock services and class instances.
   *
   * @param object[] $service_map
   *   An associative array of service instances keyed by service name.
   */
  protected function setUpMocks($service_map = []) {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('entity_test');
    $entity->expects($this->any())
      ->method('id')
      ->willReturn('id');
    $entity->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(FALSE);
    $entity->expects($this->any())
      ->method('getLoadedRevisionId')
      ->willReturn('revision_id');

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->expects($this->any())
      ->method('load')
      ->with('id')
      ->willReturn($entity);
    $storage->expects($this->any())
      ->method('getLatestRevisionId')
      ->with('id')
      ->willReturn('revision_id');

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('entity_test')
      ->willReturn($storage);

    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('isRevisionable')
      ->willReturn(TRUE);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('entity_test')
      ->willReturn($entity_type);

    $context_repository = $this->createMock(ContextRepositoryInterface::class);
    $context_repository->expects($this->any())
      ->method('getAvailableContexts')
      ->willReturn([]);

    $service_map += [
      'context.repository' => $context_repository,
    ];

    /** @var \Symfony\Component\DependencyInjection\ContainerInterface|\PHPUnit_Framework_MockObject_MockObject $container */
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($service_map);

    \Drupal::setContainer($container);
  }

  /**
   * Tests the applies() method.
   *
   * @dataProvider providerTestApplies
   *
   * @covers ::applies
   */
  public function testApplies(array $definition, $name, Route $route, $applies) {
    $this->entityTypeManager->expects($this->any())
      ->method('hasDefinition')
      ->willReturnCallback(function ($entity_type) {
        return 'entity_test' == $entity_type;
      });
    $this->assertEquals($applies, $this->entityConverter->applies($definition, $name, $route));
  }

  /**
   * Provides test data for testApplies()
   */
  public function providerTestApplies() {
    $data = [];
    $data[] = [['type' => 'entity:foo'], 'foo', new Route('/test/{foo}/bar'), FALSE];
    $data[] = [['type' => 'entity:entity_test'], 'foo', new Route('/test/{foo}/bar'), TRUE];
    $data[] = [['type' => 'entity:entity_test'], 'entity_test', new Route('/test/{entity_test}/bar'), TRUE];
    $data[] = [['type' => 'entity:{entity_test}'], 'entity_test', new Route('/test/{entity_test}/bar'), FALSE];
    $data[] = [['type' => 'entity:{entity_type}'], 'entity_test', new Route('/test/{entity_type}/{entity_test}/bar'), TRUE];
    $data[] = [['type' => 'foo'], 'entity_test', new Route('/test/{entity_type}/{entity_test}/bar'), FALSE];

    return $data;
  }

  /**
   * Tests the convert() method.
   *
   * @dataProvider providerTestConvert
   *
   * @covers ::convert
   */
  public function testConvert($value, array $definition, array $defaults, $expected_result) {
    $entity_storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('entity_test')
      ->willReturn($entity_storage);
    $entity_storage->expects($this->any())
      ->method('load')
      ->willReturnMap([
        ['valid_id', (object) ['id' => 'valid_id']],
        ['invalid_id', NULL],
      ]);

    $this->assertEquals($expected_result, $this->entityConverter->convert($value, $definition, 'foo', $defaults));
  }

  /**
   * Provides test data for testConvert
   */
  public function providerTestConvert() {
    $data = [];
    // Existing entity type.
    $data[] = ['valid_id', ['type' => 'entity:entity_test'], ['foo' => 'valid_id'], (object) ['id' => 'valid_id']];
    // Invalid ID.
    $data[] = ['invalid_id', ['type' => 'entity:entity_test'], ['foo' => 'invalid_id'], NULL];
    // Entity type placeholder.
    $data[] = ['valid_id', ['type' => 'entity:{entity_type}'], ['foo' => 'valid_id', 'entity_type' => 'entity_test'], (object) ['id' => 'valid_id']];

    return $data;
  }

  /**
   * Tests the convert() method with an invalid entity type.
   */
  public function testConvertWithInvalidEntityType() {
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('invalid_id')
      ->willThrowException(new InvalidPluginDefinitionException('invalid_id'));

    $this->setExpectedException(InvalidPluginDefinitionException::class);
    $this->entityConverter->convert('id', ['type' => 'entity:invalid_id'], 'foo', ['foo' => 'id']);
  }

  /**
   * Tests the convert() method with an invalid dynamic entity type.
   */
  public function testConvertWithInvalidDynamicEntityType() {
    $this->setExpectedException(ParamNotConvertedException::class, 'The "foo" parameter was not converted because the "invalid_id" parameter is missing');
    $this->entityConverter->convert('id', ['type' => 'entity:{invalid_id}'], 'foo', ['foo' => 'id']);
  }

  /**
   * Tests that omitting the context repository triggers a deprecation error.
   *
   * @group legacy
   *
   * @expectedDeprecation The context.repository service must be passed to EntityConverter::__construct(), it is required before Drupal 9.0.0. See https://www.drupal.org/node/2938929.
   */
  public function testDeprecatedOptionalContextRepository() {
    $this->setUpMocks();
    $this->entityConverter = new EntityConverter($this->entityTypeManager, NULL, $this->entityRepository);
  }

  /**
   * Tests that passing the language manager triggers a deprecation error.
   *
   * @group legacy
   *
   * @expectedDeprecation The language_manager service has been replaced with the context.repository service as a parameter for EntityConverter::__construct(), it is required before Drupal 9.0.0. See https://www.drupal.org/node/2938929.
   */
  public function testDeprecatedReplacedLanguageManager() {
    $this->setUpMocks();
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $this->entityConverter = new EntityConverter($this->entityTypeManager, $language_manager, $this->entityRepository);
  }

  /**
   * Tests that passing the language manager triggers a deprecation error.
   *
   * @group legacy
   *
   * @expectedDeprecation The property languageManager (language_manager service) is deprecated in Drupal\Core\ParamConverter\EntityConverter and will be removed before Drupal 9.0.0.
   */
  public function testDeprecatedLanguageManagerMethod() {
    $service_map = [
      'language_manager' => $this->createMock(LanguageManagerInterface::class),
    ];
    $this->setUpMocks($service_map);
    $this->entityConverter = new EntityConverter($this->entityTypeManager, $this->contextRepository, $this->entityRepository);
    $reflector = new \ReflectionMethod(EntityConverter::class, 'languageManager');
    $reflector->setAccessible(TRUE);
    $reflector->invoke($this->entityConverter);
  }

  /**
   * Tests that retrieving the language manager triggers a deprecation error.
   *
   * @group legacy
   *
   * @expectedDeprecation The property languageManager (language_manager service) is deprecated in Drupal\Core\ParamConverter\EntityConverter and will be removed before Drupal 9.0.0.
   */
  public function testDeprecatedLanguageManagerProperty() {
    $service_map = [
      'language_manager' => $this->createMock(LanguageManagerInterface::class),
    ];
    $this->setUpMocks($service_map);
    $this->entityConverter = new EntityConverter($this->entityTypeManager, $this->contextRepository, $this->entityRepository);
    $this->entityConverter->__get('languageManager');
  }

  /**
   * Tests that passing an invalid context repository triggers an exception.
   *
   * @group legacy
   */
  public function testDeprecatedInvalidArgument() {
    $this->setExpectedException(\InvalidArgumentException::class, 'An instance of ' . ContextRepositoryInterface::class . ' was expected.');
    $this->entityConverter = new EntityConverter($this->entityTypeManager, 'invalid argument', $this->entityRepository);
  }

}

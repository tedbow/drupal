<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the entity repository.
 *
 * @group Entity
 *
 * @coversDefaultClass \Drupal\Core\Entity\EntityRepository
 */
class EntityRepositoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'user',
    'language',
    'system',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityRepository = $this->container->get('entity.repository');

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('entity_test_mulrev');

    $this->installConfig(['system', 'language']);
    ConfigurableLanguage::createFromLangcode('it')->save();

    $this->container->get('state')->set('entity_test.translation', TRUE);
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Tests a non-revisionable and non-translatable entity type.
   *
   * @covers ::getActive
   */
  public function testGetActive() {
    $contexts = $this->getLanguageContexts('en');

    // Check that the correct active variant is returned for a non-translatable,
    // non-revisionable entity.
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('entity_test');
    $values = ['name' => $this->randomString()];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->create($values);
    $storage->save($entity);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $active */
    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($entity, $active);

    // Check that the correct active variant is returned for a non-translatable
    // revisionable entity.
    $storage = $this->entityTypeManager->getStorage('entity_test_rev');
    $values = ['name' => $this->randomString()];
    $entity = $storage->create($values);
    $storage->save($entity);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
    $revision = $storage->createRevision($entity, FALSE);
    $revision->save();
    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($revision->getLoadedRevisionId(), $active->getLoadedRevisionId());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $revision2 */
    $revision2 = $storage->createRevision($revision);
    $revision2->save();
    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($revision2->getLoadedRevisionId(), $active->getLoadedRevisionId());

    // Check that the correct active variant is returned for a translatable
    // non-revisionable entity.
    $storage = $this->entityTypeManager->getStorage('entity_test_mul');
    $values = ['name' => $this->randomString()];
    $entity = $storage->create($values);
    $storage->save($entity);

    $langcode = 'it';
    /** @var \Drupal\Core\Entity\ContentEntityInterface $translation */
    $translation = $entity->addTranslation($langcode, $values);
    $storage->save($translation);
    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($entity->language()->getId(), $active->language()->getId());

    $it_contexts = $this->getLanguageContexts($langcode);
    $active = $this->entityRepository->getActive($entity, $it_contexts);
    $this->assertSame($translation->language()->getId(), $active->language()->getId());

    // Check that the correct active variant is returned for a translatable and
    // revisionable entity.
    $storage = $this->entityTypeManager->getStorage('entity_test_mulrev');
    $values = ['name' => $this->randomString()];
    $entity = $storage->create($values);
    $storage->save($entity);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $en_revision */
    $en_revision = $storage->createRevision($entity, FALSE);
    $storage->save($en_revision);
    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($en_revision->getLoadedRevisionId(), $active->getLoadedRevisionId());

    $revision_translation = $en_revision->addTranslation($langcode, $values);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $it_revision */
    $it_revision = $storage->createRevision($revision_translation, FALSE);
    $storage->save($it_revision);

    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($en_revision->getLoadedRevisionId(), $active->getLoadedRevisionId());
    $this->assertSame($en_revision->language()->getId(), $active->language()->getId());

    $active = $this->entityRepository->getActive($entity, $it_contexts);
    $this->assertSame($it_revision->getLoadedRevisionId(), $active->getLoadedRevisionId());
    $this->assertSame($it_revision->language()->getId(), $active->language()->getId());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $en_revision2 */
    $en_revision2 = $storage->createRevision($en_revision);
    $storage->save($en_revision2);

    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($en_revision2->getLoadedRevisionId(), $active->getLoadedRevisionId());
    $this->assertSame($en_revision2->language()->getId(), $active->language()->getId());

    $active = $this->entityRepository->getActive($entity, $it_contexts);
    $this->assertSame($it_revision->getLoadedRevisionId(), $active->getLoadedRevisionId());
    $this->assertSame($it_revision->language()->getId(), $active->language()->getId());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $it_revision2 */
    $it_revision2 = $storage->createRevision($it_revision);
    $storage->save($it_revision2);

    $active = $this->entityRepository->getActive($entity, $contexts);
    $this->assertSame($it_revision2->getLoadedRevisionId(), $active->getLoadedRevisionId());
    $this->assertSame($it_revision2->getUntranslated()->language()->getId(), $active->language()->getId());

    $active = $this->entityRepository->getActive($entity, $it_contexts);
    $this->assertSame($it_revision2->getLoadedRevisionId(), $active->getLoadedRevisionId());
    $this->assertSame($it_revision2->language()->getId(), $active->language()->getId());
  }

  /**
   * Returns a set of language contexts matching the specified language.
   *
   * @param string $langcode
   *   A language code.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of contexts.
   */
  protected function getLanguageContexts($langcode) {
    return [
      new Context(new ContextDefinition('language', 'Interface text'), $langcode),
      new Context(new ContextDefinition('language', 'Content'), $langcode),
    ];
  }

}

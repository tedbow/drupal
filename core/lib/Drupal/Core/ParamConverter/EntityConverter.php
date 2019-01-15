<?php

namespace Drupal\Core\ParamConverter;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting entity IDs to full objects.
 *
 * This is useful in cases where the dynamic elements of the path can't be
 * auto-determined; for example, if your path refers to multiple of the same
 * type of entity ("example/{node1}/foo/{node2}") or if the path can act on any
 * entity type ("example/{entity_type}/{entity}/foo").
 *
 * In order to use it you should specify some additional options in your route:
 * @code
 * example.route:
 *   path: foo/{example}
 *   options:
 *     parameters:
 *       example:
 *         type: entity:node
 * @endcode
 *
 * If you want to have the entity type itself dynamic in the url you can
 * specify it like the following:
 * @code
 * example.route:
 *   path: foo/{entity_type}/{example}
 *   options:
 *     parameters:
 *       example:
 *         type: entity:{entity_type}
 * @endcode
 *
 * If your route needs to support pending revisions, you can specify the
 * "load_latest_revision" parameter. This will ensure that the latest revision
 * is returned, even if it is not the default one:
 * @code
 * example.route:
 *   path: foo/{example}
 *   options:
 *     parameters:
 *       example:
 *         type: entity:node
 *         load_latest_revision: TRUE
 * @endcode
 *
 * When dealing with translatable entities, the "load_latest_revision" flag will
 * make this converter load the latest revision affecting the translation
 * matching the content language for the current request. If none can be found
 * it will fall back to the latest revision. For instance, if an entity has an
 * English default revision (revision 1) and an Italian pending revision
 * (revision 2), "/foo/1" will return the former, while "/it/foo/1" will return
 * the latter.
 *
 * @see entities_revisions_translations
 */
class EntityConverter implements ParamConverterInterface {
  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = [
    'entityManager' => 'entity.manager',
    'languageManager' => 'language_manager',
  ];

  /**
   * Entity type manager which performs the upcasting in the end.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a new EntityConverter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $context_repository = NULL, EntityRepositoryInterface $entity_repository = NULL) {
    if ($entity_type_manager instanceof EntityManagerInterface) {
      @trigger_error('Passing the entity.manager service to EntityConverter::__construct() is deprecated in Drupal 8.7.0 and will be removed before Drupal 9.0.0. Pass the entity_type.manager service instead. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
    }
    $this->entityTypeManager = $entity_type_manager;

    if ($context_repository instanceof ContextRepositoryInterface) {
      $this->contextRepository = $context_repository;
    }
    else {
      if ($context_repository instanceof LanguageManagerInterface) {
        @trigger_error('The language_manager service has been replaced with the context.repository service as a parameter for EntityConverter::__construct(), it is required before Drupal 9.0.0. See https://www.drupal.org/node/2938929.', E_USER_DEPRECATED);
      }
      elseif (!isset($context_repository)) {
        @trigger_error('The context.repository service must be passed to EntityConverter::__construct(), it is required before Drupal 9.0.0. See https://www.drupal.org/node/2938929.', E_USER_DEPRECATED);
      }
      else {
        throw new \InvalidArgumentException('An instance of ' . ContextRepositoryInterface::class . ' was expected.');
      }
      $this->contextRepository = \Drupal::service('context.repository');
    }

    if ($entity_repository) {
      $this->entityRepository = $entity_repository;
    }
    else {
      @trigger_error('The entity.repository service must be passed to EntityConverter::__construct(), it is required before Drupal 9.0.0. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
      $this->entityRepository = \Drupal::service('entity.repository');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);

    $entity = $storage->load($value);

    // If the entity type is revisionable and the parameter has the
    // "load_latest_revision" flag, load the latest revision.
    if ($entity instanceof RevisionableInterface && !empty($definition['load_latest_revision']) && $entity_definition->isRevisionable()) {
      $contexts = $this->contextRepository->getAvailableContexts();
      $entity = $this->entityRepository->getActive($entity, $contexts);
    }

    // If the entity type is translatable, ensure we return the proper
    // translation object for the current context.
    if ($entity instanceof EntityInterface && $entity instanceof TranslatableInterface) {
      $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    }

    return $entity;
  }

  /**
   * Returns the ID of the latest revision translation of the specified entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $entity
   *   The default revision of the entity being converted.
   * @param string $langcode
   *   The language of the revision translation to be loaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface
   *   The latest translation-affecting revision for the specified entity, or
   *   just the latest revision, if the specified entity is not translatable or
   *   does not have a matching translation yet.
   */
  protected function getLatestTranslationAffectedRevision(RevisionableInterface $entity, $langcode) {
    return $this->entityRepository->getLatestTranslationAffectedRevision($entity, $langcode);
  }

  /**
   * Loads the specified entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $entity
   *   The default revision of the entity being converted.
   * @param string $revision_id
   *   The identifier of the revision to be loaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface
   *   An entity revision object.
   */
  protected function loadRevision(RevisionableInterface $entity, $revision_id) {
    // We explicitly perform a loose equality check, since a revision ID may
    // be returned as an integer or a string.
    if ($entity->getLoadedRevisionId() != $revision_id) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      return $storage->loadRevision($revision_id);
    }
    else {
      return $entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($definition['type']) && strpos($definition['type'], 'entity:') === 0) {
      $entity_type_id = substr($definition['type'], strlen('entity:'));
      if (strpos($definition['type'], '{') !== FALSE) {
        $entity_type_slug = substr($entity_type_id, 1, -1);
        return $name != $entity_type_slug && in_array($entity_type_slug, $route->compile()->getVariables(), TRUE);
      }
      return $this->entityTypeManager->hasDefinition($entity_type_id);
    }
    return FALSE;
  }

  /**
   * Determines the entity type ID given a route definition and route defaults.
   *
   * @param mixed $definition
   *   The parameter definition provided in the route options.
   * @param string $name
   *   The name of the parameter.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return string
   *   The entity type ID.
   *
   * @throws \Drupal\Core\ParamConverter\ParamNotConvertedException
   *   Thrown when the dynamic entity type is not found in the route defaults.
   */
  protected function getEntityTypeFromDefaults($definition, $name, array $defaults) {
    $entity_type_id = substr($definition['type'], strlen('entity:'));

    // If the entity type is dynamic, it will be pulled from the route defaults.
    if (strpos($entity_type_id, '{') === 0) {
      $entity_type_slug = substr($entity_type_id, 1, -1);
      if (!isset($defaults[$entity_type_slug])) {
        throw new ParamNotConvertedException(sprintf('The "%s" parameter was not converted because the "%s" parameter is missing', $name, $entity_type_slug));
      }
      $entity_type_id = $defaults[$entity_type_slug];
    }
    return $entity_type_id;
  }

  /**
   * Returns a language manager instance.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   *
   * @internal
   */
  protected function languageManager() {
    return $this->__get('languageManager');
  }

}

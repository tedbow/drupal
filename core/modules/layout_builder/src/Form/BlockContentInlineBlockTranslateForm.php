<?php

namespace Drupal\layout_builder\Form;

use Drupal\block_content\BlockContentForm;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BlockContentInlineBlockTranslateForm extends BlockContentForm {

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The component UUID.
   *
   * @var string;
   */
  protected $uuid;

  /**
   * The section delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\TranslatableSectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, RouteMatchInterface $route_match, LayoutTempstoreRepositoryInterface $tempstore) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->routeMatch = $route_match;
    $this->layoutTempstoreRepository = $tempstore;
    $this->uuid = $route_match->getParameter('uuid');
    $this->delta = $route_match->getParameter('delta');
    $this->sectionStorage = $route_match->getParameter('section_storage');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_route_match'),
      $container->get('layout_builder.tempstore_repository')
    );
  }




  /**
   * @inheritDoc
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    /** @var \Drupal\layout_builder\TranslatableSectionStorageInterface $section_storage */
    $translated_configuration = $this->sectionStorage->getTranslatedComponentConfiguration($this->uuid);
    $current_langcode = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    if (!empty($translated_configuration)) {
      if (!empty($translated_configuration['block_serialized'])) {
        return unserialize($translated_configuration['block_serialized']);
      }
      elseif (!empty($translated_configuration['block_revision_id'])) {
        /** @var \Drupal\block_content\BlockContentInterface $entity */
        $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($translated_configuration['block_revision_id']);
        $entity = $this->entityRepository->getActive('block_content', $entity->id());
        if ($entity->hasTranslation($current_langcode)) {
          return $entity->getTranslation($current_langcode);
        }
      }
    }
    $configuration = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid)->getPlugin()->getConfiguration();
    if (!empty($configuration['block_serialized'])) {
      return unserialize($configuration['block_serialized']);
    }
    elseif (!empty($configuration['block_revision_id'])) {
      /** @var \Drupal\block_content\BlockContentInterface $entity */
      $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id']);
      $entity = $this->entityRepository->getActive('block_content', $entity->id());
      if ($entity->hasTranslation($current_langcode)) {
        return $entity->getTranslation($current_langcode);
      }
      else {
        $translation = $entity->addTranslation($current_langcode, $entity->toArray());
        if (!empty($translated_configuration['label'])) {
          $translation->setInfo($translated_configuration['label']);
        }

        return $translation;
      }
    }
    else {
      throw new \LogicException("InlineBlockTranslationForm should never be invoked without an available block_content entity");
    }
  }

  /**
   * @inheritDoc
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $inline_block */
    $inline_block = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid)->getPlugin();

    $translated_configuration = $this->sectionStorage->getTranslatedComponentConfiguration($this->uuid);
    $translated_configuration['block_serialized'] = serialize($entity);
    $translated_configuration['label'] = $entity->label();
    $this->sectionStorage->setTranslatedComponentConfiguration($this->uuid, $translated_configuration);
    $this->layoutTempstoreRepository->set($this->sectionStorage);
  }

  /**
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *
   * @return \Drupal\layout_builder\Plugin\Block\InlineBlock
   */
  protected function getInlineBlockPlugin(RouteMatchInterface $route_match) {
    /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
    $section_storage = $route_match->getParameter('section_storage');
    $section = $section_storage->getSection($route_match->getParameter('delta'));
    $component = $section->getComponent($route_match->getParameter('uuid'));
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $inline_block */
    $inline_block = $component->getPlugin();
    return $inline_block;
  }


}

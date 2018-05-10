<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Event\PrepareLayoutForUiEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PrepareLayoutForUiSubscriber
 *
 * @package Drupal\layout_builder\EventSubscriber
 */
class PrepareLayoutForUiSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a PrepareLayoutForUiSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::PREPARE_SECTIONS_FOR_UI] = ['onPrepareLayout', 100];
    return $events;
  }

  /**
   * Prepare sections for UI.
   *
   * @param \Drupal\layout_builder\Event\PrepareLayoutForUiEvent $event
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onPrepareLayout(PrepareLayoutForUiEvent $event) {
    $section_storage = $event->getSectionStorage();
    if (!$event->isRebuilding() && count($event->getOriginalSections()) === 0 && $section_storage instanceof OverridesSectionStorageInterface) {
      foreach ($section_storage->getSections() as $section) {
        $components = $section->getComponents();
        foreach ($components as $component) {
          $plugin = $component->getPlugin();
          if ($plugin instanceof DerivativeInspectionInterface) {
            if ($plugin->getBaseId() === 'inline_block_content') {
              $configuration = $component->getConfiguration();
              if (!empty($configuration['block_revision_id'])) {
                $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id']);
                $duplicated_entity = $entity->createDuplicate();
                $configuration['block_revision_id'] = NULL;
                $configuration['block_serialized'] = serialize($duplicated_entity);
                $component->setConfiguration($configuration);
              }
            }
          }
        }

      }
    }
  }

}

<?php

namespace Drupal\workspace;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of workspace entities.
 *
 * @see \Drupal\workspace\Entity\Workspace
 */
class WorkspaceListBuilder extends EntityListBuilder {

  use AjaxHelperTrait;

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\workspace\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($entity_type, $storage);
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('workspace.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Workspace');
    $header['uid'] = $this->t('Owner');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\workspace\WorkspaceInterface $entity */
    $row['data'] = [
      'label' => $entity->label(),
      'owner' => $entity->getOwner()->getDisplayname(),
    ];
    $row['data'] = $row['data'] + parent::buildRow($entity);


    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if ($entity->id() === $active_workspace->id()) {
      $row['class'] = 'active-workspace';
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\workspace\WorkspaceInterface $entity */
    $operations = parent::getDefaultOperations($entity);
    if (isset($operations['edit'])) {
      $operations['edit']['query']['destination'] = $entity->toUrl('collection')->toString();
    }

    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if ($entity->id() != $active_workspace->id()) {
      $operations['activate'] = [
        'title' => $this->t('Switch to @workspace', ['@workspace' => $entity->label()]),
        // Use a weight lower than the one of the 'Edit' operation because we
        // want the 'Activate' operation to be the primary operation.
        'weight' => 0,
        'url' => $entity->toUrl('activate-form', ['query' => ['destination' => $entity->toUrl('collection')->toString()]]),
      ];
    }

    if (!$entity->isDefaultWorkspace()) {
      $operations['deploy'] = [
        'title' => $this->t('Deploy content'),
        // The 'Deploy' operation should be the default one for the currently
        // active workspace.
        'weight' => ($entity->id() == $active_workspace->id()) ? 0 : 20,
        'url' => $entity->toUrl('deploy-form', ['query' => ['destination' => $entity->toUrl('collection')->toString()]]),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities =  parent::load();
    // Make the active workspace more visible by moving it first in the list.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    $entities = [$active_workspace->id() => $entities[$active_workspace->id()]] + $entities;
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    if ($this->isAjax()) {
      $this->offCanvasRender($build);
    }
    else {
      $build['#attached'] = [
        'library' => ['workspace/drupal.workspace.overview'],
      ];
    }
    return $build;
  }

  /**
   * Renders the off canvas elements.
   *
   * @param array $build
   *   A render array.
   */
  protected function offCanvasRender(array &$build) {
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    $default_class = $active_workspace->isDefaultWorkspace() ? 'default' : 'not-default';
    $row_count = count($build['table']['#rows']);
    $build['active_workspace'] = [
      '#type' => 'container',
      '#weight' => -20,
      '#attributes' => [
        'class' => ['active-workspace', $default_class, $active_workspace->id()],
      ],
      'label' => [
        '#type' => 'label',
        '#prefix' => $this->t('<span>Current workspace:</span>'),
        '#title' => $active_workspace->label(),
        '#title_display' => '',
      ],
      'manage' => [
        '#type' => 'link',
        '#title' => $this->t('Manage workspaces'),
        '#url' => $active_workspace->toUrl('collection'),
        '#attributes' => [
          'class' => ['manage'],
        ],
      ],
    ];
    if (!$active_workspace->isDefaultWorkspace()) {
      $build['active_workspace']['actions'] = [
        '#type' => 'container',
        '#weight' => 20,
        '#attributes' => [
          'class' => ['actions'],
        ],
        'deploy' => [
          '#type' => 'link',
          '#title' => $this->t('Deploy content'),
          '#url' => $active_workspace->toUrl('deploy-form', ['query' => ['destination' => $active_workspace->toUrl('collection')->toString()]]),
          '#attributes' => [
            'class' => 'button',
          ],
        ],
      ];
    }
    if ($row_count > 1) {
      $build['all_workspaces'] = [
        '#type' => 'link',
        '#title' => $this->t('View all @count workspaces', ['@count' => $row_count]),
        '#url' => $active_workspace->toUrl('collection'),
        '#attributes' => [
          'class' => ['all-workspaces'],
        ],
      ];
    }
    $items = [];
    $rows = array_slice($build['table']['#rows'], 0, 4, TRUE);
    foreach ($rows as $id => $row) {
      if ($active_workspace->id() !== $id) {
        $url = Url::fromRoute('entity.workspace.activate_form', ['workspace' => $id]);
        $default_class = $id === WorkspaceInterface::DEFAULT_WORKSPACE ? 'default' : 'not-default';
        $items[] = [
          '#type' => 'link',
          '#title' => $row['data']['label'],
          '#url' => $url,
          '#attributes' => [
            'class' => ['use-ajax', $default_class],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => 500,
            ]),
          ],
        ];
      }
    }
    $build['workspaces'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];
    unset($build['table']);
    unset($build['pager']);
  }

}

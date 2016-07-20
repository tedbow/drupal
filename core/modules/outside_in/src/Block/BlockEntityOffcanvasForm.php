<?php

namespace Drupal\outside_in\Block;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo.
 */
class BlockEntityOffCanvasForm extends EntityForm {

  /**
   * The block entity.
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $entity;

  /**
   * The block manager.
   *
   * @var \Drupal\outside_in\Block\OutsideInBlockManagerInterface
   */
  protected $blockManager;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * BlockEntityOffCanvasForm constructor.
   *
   * @param \Drupal\outside_in\Block\OutsideInBlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The lazy context repository service.
   */
  public function __construct(OutsideInBlockManagerInterface $block_manager, ContextRepositoryInterface $context_repository) {
    $this->contextRepository = $context_repository;
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('outside_in.block.manager'),
      $container->get('context.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Store theme settings in $form_state for use below.
    $form_state->set('block_theme', $this->entity->getTheme());

    // Store the gathered contexts in the form state for other objects to use
    // during form building.
    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'block/drupal.block.admin';

    $form['settings'] = $this->blockManager->getFormObject($this->entity->getPlugin(), 'sidebar')->buildConfigurationForm([], $form_state);

    return $form;
  }

}

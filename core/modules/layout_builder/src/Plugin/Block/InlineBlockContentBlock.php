<?php

namespace Drupal\layout_builder\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessDependentInterface;
use Drupal\Core\Access\AccessDependentTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\EntityUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an inline custom block type.
 *
 * @Block(
 *  id = "inline_block_content",
 *  admin_label = @Translation("Inline custom block"),
 *  category = @Translation("Inline custom blocks"),
 *  deriver = "Drupal\layout_builder\Plugin\Derivative\InlineBlockContentDeriver",
 * )
 */
class InlineBlockContentBlock extends BlockBase implements ContainerFactoryPluginInterface, AccessDependentInterface {

  use AccessDependentTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal account to use for checking for access to block.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The block content entity.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $blockContent;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Whether a new serialized block is being created.
   *
   * @var bool
   */
  protected $isNew = TRUE;

  /**
   * The entity usage tracker service.
   *
   * @var \Drupal\layout_builder\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * Constructs a new InlineBlockContentBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which view access should be checked.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\layout_builder\EntityUsageInterface $entity_usage
   *   The entity usage service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AccountInterface $account, EntityDisplayRepositoryInterface $entity_display_repository, EntityUsageInterface $entity_usage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityUsage = $entity_usage;
    if (!empty($this->configuration['block_revision_id']) || !empty($this->configuration['block_serialized'])) {
      $this->isNew = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity_display.repository'),
      $container->get('entity.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'view_mode' => 'full',
      'block_revision_id' => NULL,
      'block_serialized' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $block = $this->getEntity();

    // Add the entity form display in a process callback so that #parents can
    // be successfully propagated to field widgets.
    $form['block_form'] = [
      '#type' => 'container',
      '#process' => [[static::class, 'processBlockForm']],
      '#block' => $block,
    ];

    $options = $this->entityDisplayRepository->getViewModeOptionsByBundle('block_content', $block->bundle());

    $form['view_mode'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('View mode'),
      '#description' => $this->t('The view mode in which to render the block.'),
      '#default_value' => $this->configuration['view_mode'],
      '#access' => count($options) > 1,
    ];
    $form['title']['#description'] = $this->t('The title of the block as shown to the user.');
    return $form;
  }

  /**
   * Process callback to insert a Custom Block form.
   *
   * @param array $element
   *   The containing element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The containing element, with the Custom Block form inserted.
   */
  public static function processBlockForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $element['#block'];
    EntityFormDisplay::collectRenderDisplay($block, 'edit')->buildForm($block, $element, $form_state);
    $element['revision_log']['#access'] = FALSE;
    $element['info']['#access'] = FALSE;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $block_form = $form['block_form'];
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $block_form['#block'];
    $form_display = EntityFormDisplay::collectRenderDisplay($block, 'edit');
    $complete_form_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
    $form_display->extractFormValues($block, $block_form, $complete_form_state);
    $form_display->validateFormValues($block, $block_form, $complete_form_state);
    // @todo Remove when https://www.drupal.org/project/drupal/issues/2948549 is closed.
    $form_state->setTemporaryValue('block_form_parents', $block_form['#parents']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');

    // @todo Remove when https://www.drupal.org/project/drupal/issues/2948549 is closed.
    $block_form = NestedArray::getValue($form, $form_state->getTemporaryValue('block_form_parents'));
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $block_form['#block'];
    $form_display = EntityFormDisplay::collectRenderDisplay($block, 'edit');
    $complete_form_state = ($form_state instanceof SubformStateInterface) ? $form_state->getCompleteFormState() : $form_state;
    $form_display->extractFormValues($block, $block_form, $complete_form_state);
    $block->setInfo($this->configuration['label']);
    $this->configuration['block_serialized'] = serialize($block);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($this->getEntity()) {
      return $this->getEntity()->access('view', $account, TRUE);
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = $this->getEntity();
    return $this->entityTypeManager->getViewBuilder($block->getEntityTypeId())->view($block, $this->configuration['view_mode']);
  }

  /**
   * Loads or creates the block content entity of the block.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   The block content entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntity() {
    if (!isset($this->blockContent)) {
      if (!empty($this->configuration['block_serialized'])) {
        $this->blockContent = unserialize($this->configuration['block_serialized']);
      }
      elseif (!empty($this->configuration['block_revision_id'])) {
        $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($this->configuration['block_revision_id']);
        $this->blockContent = $entity;
      }
      else {
        $this->blockContent = $this->entityTypeManager->getStorage('block_content')->create([
          'type' => $this->getDerivativeId(),
          'reusable' => FALSE,
        ]);
      }
    }
    if ($this->blockContent instanceof AccessDependentInterface && $dependee = $this->getAccessDependee()) {
      $this->blockContent->setAccessDependee($dependee);
    }
    return $this->blockContent;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    if ($this->isNew) {
      // If the Content Block is new then don't provide a default label.
      // @todo Blocks may be serialized before the layout is saved so we
      // can't check $this->getEntity()->isNew().
      unset($form['label']['#default_value']);
    }
    return $form;
  }

  /**
   * Saves the "block_content" entity for this plugin.
   *
   * @param bool $new_revision
   *   Whether to create new revision.
   * @param bool $duplicate_block
   *   Whether to duplicate the "block_content" entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $parent_entity
   *   The parent entity if any that is using this plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function saveBlockContent($new_revision = FALSE, $duplicate_block = FALSE, EntityInterface $parent_entity = NULL) {
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = NULL;
    if ($duplicate_block && !empty($this->configuration['block_revision_id']) && empty($this->configuration['block_serialized'])) {
      $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($this->configuration['block_revision_id']);
      $block = $entity->createDuplicate();
    }
    elseif (isset($this->configuration['block_serialized'])) {
      $block = unserialize($this->configuration['block_serialized']);
      if (!empty($this->configuration['block_revision_id']) && $duplicate_block) {
        $block = $block->createDuplicate();
      }
    }
    if ($block) {
      $new_usage = $block->isNew();
      if ($new_revision) {
        $block->setNewRevision();
      }
      $block->save();
      $this->configuration['block_revision_id'] = $block->getRevisionId();
      $this->configuration['block_serialized'] = NULL;
      if ($new_usage && $parent_entity) {
        $this->entityUsage->addByEntities($block, $parent_entity);
      }
    }
  }

}

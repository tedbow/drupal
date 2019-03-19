<?php

namespace Drupal\layout_builder\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\layout_builder\Form\BlockPluginTranslationForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Layout Builder inline block translation form..
 */
class InlineBlockTranslationForm extends BlockPluginTranslationForm {

  /**
   * The inline block plugin.
   *
   * @var \Drupal\layout_builder\Plugin\Block\InlineBlock
   */
  protected $plugin;


  /**
   * The block content entity.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $blockContent;

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
   * InlineBlockTranslationForm constructor.
   *
   * @param string $current_langcode
   *   The current language code.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($current_langcode, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    parent::__construct($current_langcode);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')->getCurrentLanguage()->getId(),
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntity() {
    if (!empty($this->translatedConfiguration)) {
      if (!empty($this->translatedConfiguration['block_serialized'])) {
        return unserialize($this->translatedConfiguration['block_serialized']);
      }
      elseif (!empty($this->translatedConfiguration['block_revision_id'])) {
        /** @var \Drupal\block_content\BlockContentInterface $entity */
        $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($this->translatedConfiguration['block_revision_id']);
        $entity = $this->entityRepository->getActive('block_content', $entity->id());
        if ($entity->hasTranslation($this->currentLangcode)) {
          return $entity->getTranslation($this->currentLangcode);
        }
      }
    }
    $configuration = $this->plugin->getConfiguration();
    if (!empty($configuration['block_serialized'])) {
      return unserialize($configuration['block_serialized']);
    }
    elseif (!empty($configuration['block_revision_id'])) {
      /** @var \Drupal\block_content\BlockContentInterface $entity */
      $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id']);
      $entity = $this->entityRepository->getActive('block_content', $entity->id());
      if ($entity->hasTranslation($this->currentLangcode)) {
        return $entity->getTranslation($this->currentLangcode);
      }
      else {
        return $entity->addTranslation($this->currentLangcode, $entity->toArray());
      }
    }
    else {
      throw new \LogicException("InlineBlockTranslationForm should never be invoked without an available block_content entity");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $block = $this->getEntity();
    if ($block->isTranslatable()) {
      // Add the entity form display in a process callback so that #parents can
      // be successfully propagated to field widgets.
      $form['block_form'] = [
        '#type' => 'container',
        '#process' => [[static::class, 'processBlockForm']],
        '#block' => $block,
      ];
    }
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
    // @todo (in this issue) Look at how ContentTranslationController creates
    //   the add/edit translation forms and determine if we need to implement
    //   the same logic.
    EntityFormDisplay::collectRenderDisplay($block, 'edit')->buildForm($block, $element, $form_state);
    $element['revision_log']['#access'] = FALSE;
    $element['info']['#access'] = FALSE;
    $element['langcode']['#access'] = FALSE;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if (!empty($form['block_form'])) {
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

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!empty($form['settings']['block_form'])) {
      // @todo Remove when https://www.drupal.org/project/drupal/issues/2948549 is closed.
      $block_form = NestedArray::getValue($form, $form_state->getTemporaryValue('block_form_parents'));
      /** @var \Drupal\block_content\BlockContentInterface $block */
      $block = $block_form['#block'];
      $form_display = EntityFormDisplay::collectRenderDisplay($block, 'edit');
      $complete_form_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
      $form_display->extractFormValues($block, $block_form, $complete_form_state);

      $form_state->setValue('block_serialized', serialize($block));
      $form_state->unsetValue('block_form');
    }
  }

}

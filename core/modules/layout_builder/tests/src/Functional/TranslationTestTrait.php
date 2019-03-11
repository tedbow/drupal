<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;

/**
 * Common functions for testing Layout Builder with translations.
 */
trait TranslationTestTrait {

  /**
   * The entity used for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    $permissions = parent::getAdministratorPermissions();
    $permissions[] = 'administer entity_test_mul display';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatorPermissions() {
    $permissions = parent::getTranslatorPermissions();
    $permissions[] = 'view test entity translations';
    $permissions[] = 'view test entity';
    $permissions[] = 'configure any layout';
    return $permissions;
  }

  /**
   * Setup translated entity with layouts.
   */
  protected function setUpEntities() {
    $this->drupalLogin($this->administrator);

    $field_ui_prefix = 'entity_test_mul/structure/entity_test_mul';
    // Allow overrides for the layout.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[enabled]' => TRUE], 'Save');
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create a test entity.
    $id = $this->createEntity([
      $this->fieldName => [['value' => 'The untranslated field value']],
    ], $this->langcodes[0]);
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityTypeId);
    $storage->resetCache([$id]);
    $this->entity = $storage->load($id);

    // Create a translation.
    $this->drupalLogin($this->translator);
    $add_translation_url = Url::fromRoute("entity.$this->entityTypeId.content_translation_add", [
      $this->entityTypeId => $this->entity->id(),
      'source' => $this->langcodes[0],
      'target' => $this->langcodes[2],
    ]);
    $this->drupalPostForm($add_translation_url, [
      "{$this->fieldName}[0][value]" => 'The translated field value',
    ], 'Save');
  }

  /**
   * Set up the View Display.
   */
  protected function setUpViewDisplay() {
    EntityViewDisplay::create([
      'targetEntityType' => $this->entityTypeId,
      'bundle' => $this->bundle,
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($this->fieldName, ['type' => 'string'])->save();
  }

}

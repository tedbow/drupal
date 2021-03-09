<?php

namespace Drupal\update\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\FileTransfer\Local;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Updater\Updater;
use Drupal\update\ComposerUpdater;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configure update settings for this site.
 *
 * @internal
 */
class UpdateReady extends FormBase {

  /**
   * The root location under which updated projects will be saved.
   *
   * @var string
   */
  protected $root;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Site path.
   *
   * @var string
   */
  protected $sitePath;

  /**
   * Constructs a new UpdateReady object.
   *
   * @param string $root
   *   The root location under which updated projects will be saved.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The object that manages enabled modules in a Drupal installation.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param string $site_path
   *   The site path.
   */
  public function __construct($root, ModuleHandlerInterface $module_handler, StateInterface $state, $site_path) {
    $this->root = $root;
    $this->moduleHandler = $module_handler;
    $this->state = $state;
    $this->sitePath = $site_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'update_manager_update_ready_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('update.root'),
      $container->get('module_handler'),
      $container->get('state'),
      $container->getParameter('site.path')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->moduleHandler->loadInclude('update', 'inc', 'update.manager');
    if (!_update_manager_check_backends($form, 'update')) {
      return $form;
    }

    $form['backup'] = [
      '#prefix' => '<strong>',
      '#markup' => $this->t('Back up your database and site before you continue. <a href=":backup_url">Learn how</a>.', [':backup_url' => 'https://www.drupal.org/node/22281']),
      '#suffix' => '</strong>',
    ];

    $form['maintenance_mode'] = [
      '#title' => $this->t('Perform updates with site in maintenance mode (strongly recommended)'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $session = $this->getRequest()->getSession();
    // Store maintenance_mode setting so we can restore it when done.
    $session->set('maintenance_mode', $this->state->get('system.maintenance_mode'));
    if ($form_state->getValue('maintenance_mode') == TRUE) {
      $this->state->set('system.maintenance_mode', TRUE);
    }

    $projects = $session->remove('update_manager_update_projects');
    if ($projects) {
      if (ComposerUpdater::copyStaged()) {
        \Drupal::messenger()->addMessage("Update success full. @todo Run update DB.");
        $form_state->setRedirect('system.db_update');
      }
      else {
        \Drupal::messenger()->addError("Update not success full.");
        return $this->redirect('update.report_update');
      }

    }
  }

}

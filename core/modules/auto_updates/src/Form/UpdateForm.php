<?php

namespace Drupal\auto_updates\Form;

use Composer\Semver\Semver;
use Drupal\auto_updates\UpdateCalculator;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\system\ExtensionVersion;
use Drupal\update\ComposerUpdater;
use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateManagerInterface;
use Drupal\update\ModuleVersion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure update settings for this site.
 *
 * @internal
 */
class UpdateForm extends FormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Drupal state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\auto_updates\UpdateCalculator
   */
  protected $updateCalculator;

  /**
   * Constructs a new UpdateManagerUpdate object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, StateInterface $state, UpdateCalculator $update_calculator) {
    $this->moduleHandler = $module_handler;
    $this->state = $state;
    $this->updateCalculator = $update_calculator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_updates_update_for';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('state'),
      $container->get('auto_updates.calculator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $last_markup = [
      '#theme' => 'update_last_check',
      '#last' => $this->state->get('update.last_check', 0),
    ];
    $form['last_check'] = [
      '#markup' => \Drupal::service('renderer')->render($last_markup),
    ];

    $available = update_get_available(TRUE);
    if (empty($available)) {
      $form['message'] = [
        '#markup' => $this->t('There was a problem getting update information. Try again later.'),
      ];
      return $form;
    }

    $form['#attached']['library'][] = 'update/drupal.update.admin';

    // This will be a nested array. The first key is the kind of project, which
    // can be either 'enabled', 'disabled', 'manual' (projects which require
    // manual updates, such as core). Then, each subarray is an array of
    // projects of that type, indexed by project short name, and containing an
    // array of data for cells in that project's row in the appropriate table.
    $projects = [];

    // This stores the actual download link we're going to update from for each
    // project in the form, regardless of if it's enabled or disabled.
    $form['project_downloads'] = ['#tree' => TRUE];
    $form['project_versions'] = ['#tree' => TRUE];
    $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');
    // Only calculate available updates for Drupal core.
    $available = ['drupal' => $available['drupal']];
    $project_data = update_calculate_project_data($available);
    if (empty($project_data['drupal']) || $project_data['drupal']['status'] === UpdateFetcherInterface::NOT_FETCHED) {
      // @todo Error message
      $this->messenger()->addError('No available update information');
      return $form;
    }
    $project = $project_data['drupal'];
    if ($project['status'] === UpdateFetcherInterface::NOT_FETCHED) {
      $message = ['#theme' => 'update_fetch_error_message'];
      $this->messenger()->addError(\Drupal::service('renderer')->renderPlain($message));
    }

    // Filter out projects which are up to date already.
    if ($project['status'] === UpdateManagerInterface::CURRENT) {
      $this->messenger()->addMessage('There are no updates available');
      return $form;
    }

    // @todo will the project name ever vary for Drupal core.
    if (!empty($project['title'])) {
      if (!empty($project['link'])) {
        $project_name = Link::fromTextAndUrl($project['title'], Url::fromUri($project['link']))->toString();
      }
      else {
        $project_name = $project['title'];
      }
    }
    elseif (!empty($project['info']['name'])) {
      $project_name = $project['info']['name'];
    }
    else {
      $project_name = 'Drupal';
    }

    if (empty($project['recommended'])) {
      // If we don't know what to recommend they upgrade to, we should skip
      // the project entirely.
      $this->messenger()->addError('No available update information');
    }

    $supported_release = $this->updateCalculator->getSupportedUpdateRelease();
    if (!$supported_release) {
      $this->messenger()->addWarning("No supported release");
      return $form;
    }
    $supported_version_markup = '{{ release_version }} (<a href="{{ release_link }}" title="{{ project_title }}">{{ release_notes }}</a>)';

    $supported_version_markup = [
      '#type' => 'inline_template',
      '#template' => $supported_version_markup,
      '#context' => [
        'release_version' => $supported_release['version'],
        'release_link' => $supported_release['release_link'],
        'project_title' => $this->t('Release notes for @project_title', ['@project_title' => $project['title']]),
        'release_notes' => $this->t('Release notes'),
      ],
    ];

    // Create an entry for this project.
    $entry = [
      'title' => $project_name,
      'installed_version' => $project['existing_version'],
      'recommended_version' => ['data' => $supported_version_markup],
    ];



    // Use the project title for the tableselect checkboxes.
    $entry['title'] = [
      'data' => [
        '#title' => $entry['title'],
        '#markup' => $entry['title'],
      ],
    ];
    switch ($project['status']) {
      case UpdateManagerInterface::NOT_SECURE:
      case UpdateManagerInterface::REVOKED:
      $entry['title']['data']['#markup']  .= ' ' . $this->t('(Security update)');
        $type = 'security';
        break;

      case UpdateManagerInterface::NOT_SUPPORTED:
        $type = 'unsupported';
        $entry['title']['data']['#markup']  .= ' ' . $this->t('(Unsupported)');
        $entry['#weight'] = -1;
        break;

      case UpdateFetcherInterface::UNKNOWN:
      case UpdateFetcherInterface::NOT_FETCHED:
      case UpdateFetcherInterface::NOT_CHECKED:
      case UpdateManagerInterface::NOT_CURRENT:
        $type = 'recommended';
        break;

      default:
        // Unknown status.
        return $form;
    }
    $form['recommended_version']= [
      '#type' => 'value',
      '#value' => $supported_release['version'],
    ];

    $headers = [
      'title' => [
        'data' => $this->t('Name'),
        'class' => ['update-project-name'],
      ],
      'installed_version' => $this->t('Installed version'),
      'recommended_version' => $this->t('Recommended version'),
    ];
    $form['update_table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => [$entry],
      //'#prefix' => $prefix,
    ];


    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download these updates'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('projects')) {
      $enabled = array_filter($form_state->getValue('projects'));
    }
    if (!$form_state->isValueEmpty('disabled_projects')) {
      $disabled = array_filter($form_state->getValue('disabled_projects'));
    }
    if (empty($enabled) && empty($disabled)) {
      $form_state->setErrorByName('projects', $this->t('You must select at least one project to update.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->moduleHandler->loadInclude('update', 'inc', 'update.manager');
    $projects = [];
    foreach (['projects', 'disabled_projects'] as $type) {
      if (!$form_state->isValueEmpty($type)) {
        $projects = array_merge($projects, array_keys(array_filter($form_state->getValue($type))));
      }
    }
    $update_method = $this->getUpdateMethod();
    if ($update_method === 'composer') {
      $projects_versions = [];
      foreach ($projects as $project) {
        $projects_versions[$project] = $form_state->getValue(['project_versions', $project]);
      }
      $batch = [
        'title' => $this->t('Downloading updates'),
        'init_message' => $this->t('Preparing to download selected updates'),
        'operations' => [[[ComposerUpdater::class, 'processBatch'], [$projects_versions]]],
        'finished' => 'update_manager_download_batch_finished',
        'file' => drupal_get_path('module', 'update') . '/update.manager.inc',
      ];
      batch_set($batch);
      return;
    }
    $operations = [];
    foreach ($projects as $project) {
      $operations[] = [
        'update_manager_batch_project_get',
        [
          $project,
          $form_state->getValue(['project_downloads', $project]),
        ],
      ];
    }
    $batch = [
      'title' => $this->t('Downloading updates'),
      'init_message' => $this->t('Preparing to download selected updates'),
      'operations' => $operations,
      'finished' => 'update_manager_download_batch_finished',
      'file' => drupal_get_path('module', 'update') . '/update.manager.inc',
    ];
    batch_set($batch);
  }

  /**
   * Gets the current update method.
   */
  protected function getUpdateMethod(): string {
    // @todo Add UI setting.
    return $this->state->get('update.update_method', 'composer');
  }

}

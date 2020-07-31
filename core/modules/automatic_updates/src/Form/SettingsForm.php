<?php

namespace Drupal\automatic_updates\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for automatic updates.
 */
class SettingsForm extends ConfigFormBase {
  /**
   * The readiness checker.
   *
   * @var \Drupal\automatic_updates\ReadinessChecker\ReadinessCheckerManagerInterface
   */
  protected $checker;

  /**
   * The data formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Drupal root path.
   *
   * @var string
   */
  protected $drupalRoot;

  /**
   * The update manager service.
   *
   * @var \Drupal\update\UpdateManagerInterface
   */
  protected $updateManager;

  /**
   * The update processor.
   *
   * @var \Drupal\update\UpdateProcessorInterface
   */
  protected $updateProcessor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->checker = $container->get('automatic_updates.readiness_checker');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->drupalRoot = (string) $container->get('app.root');
    $instance->updateManager = $container->get('update.manager');
    $instance->updateProcessor = $container->get('update.processor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'automatic_updates.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'automatic_updates_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('automatic_updates.settings');

    $form['readiness'] = [
      '#type' => 'details',
      '#title' => $this->t('Readiness checks'),
      '#open' => TRUE,
    ];

    $last_check_timestamp = $this->checker->timestamp();
    $form['readiness']['enable_readiness_checks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check the readiness of automatically updating the site.'),
      '#default_value' => $config->get('enable_readiness_checks'),
    ];
    if ($this->checker->isEnabled()) {
      $form['readiness']['enable_readiness_checks']['#description'] = $this->t('Readiness checks were last run @time ago. Manually <a href="@link">run the readiness checks</a>.', [
        '@time' => $this->dateFormatter->formatTimeDiffSince($last_check_timestamp),
        '@link' => Url::fromRoute('automatic_updates.update_readiness')->toString(),
      ]);
    }
    $form['readiness']['ignored_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths to ignore for readiness checks'),
      '#description' => $this->t('Paths relative to %drupal_root. One path per line. Automatic Updates is intentionally limited to Drupal core. It is recommended to ignore paths to contrib extensions.', ['%drupal_root' => $this->drupalRoot]),
      '#default_value' => $config->get('ignored_paths'),
      '#states' => [
        'visible' => [
          ':input[name="enable_readiness_checks"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->cleanValues();
    $config = $this->config('automatic_updates.settings');
    foreach ($form_state->getValues() as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
  }

}

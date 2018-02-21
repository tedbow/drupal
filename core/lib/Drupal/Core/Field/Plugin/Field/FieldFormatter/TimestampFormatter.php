<?php

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'timestamp' formatter.
 *
 * @FieldFormatter(
 *   id = "timestamp",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "timestamp",
 *     "created",
 *     "changed",
 *   }
 * )
 */
class TimestampFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The date format entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dateFormatStorage;

  /**
   * Constructs a new TimestampFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $date_format_storage
   *   The date format storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DateFormatterInterface $date_formatter, EntityStorageInterface $date_format_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->dateFormatter = $date_formatter;
    $this->dateFormatStorage = $date_format_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('entity.manager')->getStorage('date_format')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'date_format' => 'medium',
      'custom_date_format' => '',
      'timezone' => '',
      'tooltip' => [
        'date_format' => 'long',
        'custom_date_format' => '',
      ],
      'timeago' => [
        'enabled' => FALSE,
        'future_format' => '@interval hence',
        'past_format' => '@interval ago',
        'granularity' => 2,
        'refresh' => 60,
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $date_formats = [];
    foreach ($this->dateFormatStorage->loadMultiple() as $machine_name => $value) {
      $date_formats[$machine_name] = $this->t('@name format: @date', ['@name' => $value->label(), '@date' => $this->dateFormatter->format(REQUEST_TIME, $machine_name)]);
    }
    $date_formats['custom'] = $this->t('Custom');

    $form['date_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Date format'),
      '#options' => $date_formats,
      '#default_value' => $this->getSetting('date_format') ?: 'medium',
    ];

    $form['custom_date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom date format'),
      '#description' => $this->t('See <a href="http://php.net/manual/function.date.php" target="_blank">the documentation for PHP date formats</a>.'),
      '#default_value' => $this->getSetting('custom_date_format') ?: '',
      '#states' => $this->buildStates(['date_format'], ['value' => 'custom']),
    ];

    $form['timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Time zone'),
      '#options' => ['' => $this->t('- Default site/user time zone -')] + system_time_zones(FALSE, TRUE),
      '#default_value' => $this->getSetting('timezone'),
    ];

    $tooltip = $this->getSetting('tooltip');
    $form['tooltip']['#tree'] = TRUE;
    $form['tooltip']['date_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Tooltip date format'),
      '#description' => $this->t('Select the date format to be used for the title and displayed on mouse hover.'),
      '#options' => $date_formats,
      '#default_value' => $tooltip['date_format'],
    ];

    $form['tooltip']['custom_date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tooltip custom date format'),
      '#description' => $this->t('See <a href="http://php.net/manual/function.date.php" target="_blank">the documentation for PHP date formats</a>.'),
      '#default_value' => $tooltip['custom_date_format'],
      '#states' => $this->buildStates(['tooltip', 'date_format'], ['value' => 'custom']),
    ];

    $timeago = $this->getSetting('timeago');
    $form['timeago']['#tree'] = TRUE;
    $form['timeago']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Display as 'time ago'"),
      '#description' => $this->t("If checked, the timestamp will be displayed as a 'time ago' string, in javascript enabled browsers. With javascript disabled, the main behavior is in place."),
      '#default_value' => $timeago['enabled'],
    ];

    $states = $this->buildStates(['timeago', 'enabled'], ['checked' => TRUE]);

    $form['timeago']['future_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Future format'),
      '#description' => $this->t('Use <em>@interval</em> where you want the formatted interval text to appear.'),
      '#default_value' => $timeago['future_format'],
      '#states' => $states,
    ];

    $form['timeago']['past_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Past format'),
      '#description' => $this->t('Use <em>@interval</em> where you want the formatted interval text to appear.'),
      '#default_value' => $timeago['past_format'],
      '#states' => $states,
    ];

    $form['timeago']['granularity'] = [
      '#type' => 'number',
      '#title' => $this->t('Granularity'),
      '#description' => $this->t('How many time interval units should be shown in the formatted output.'),
      '#default_value' => $timeago['granularity'],
      '#min' => 1,
      '#max' => 7,
      '#states' => $states,
    ];

    $form['timeago']['refresh'] = [
      '#type' => 'select',
      '#title' => $this->t('Refresh interval (seconds)'),
      '#description' => $this->t("The interval to refresh the displayed 'time ago'."),
      '#default_value' => $timeago['refresh'],
      '#options' => $this->getRefreshIntervals(),
      '#states' => $states,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $date_format = $this->getSetting('date_format');
    $summary[] = $this->t('Date format: @date_format', ['@date_format' => $date_format]);
    if ($date_format === 'custom' && ($custom_date_format = $this->getSetting('custom_date_format'))) {
      $summary[] = $this->t('Custom date format: @custom_date_format', ['@custom_date_format' => $custom_date_format]);
    }
    if ($timezone = $this->getSetting('timezone')) {
      $summary[] = $this->t('Time zone: @timezone', ['@timezone' => $timezone]);
    }

    $tooltip = $this->getSetting('tooltip');
    $summary[] = $this->t('Tooltip date format: @date_format', ['@date_format' => $tooltip['date_format']]);
    if ($tooltip['date_format'] === 'custom' && $tooltip['custom_date_format']) {
      $summary[] = $this->t('Tooltip custom date format: @custom_date_format', ['@custom_date_format' => $tooltip['custom_date_format']]);
    }

    $timeago = $this->getSetting('timeago');
    if ($timeago['enabled']) {
      $summary[] = $this->t("Displayed as 'time ago'");

      $options = ['granularity' => $timeago['granularity']];

      $timestamp = strtotime('1 year 1 month 1 week 1 day 1 hour 1 minute');
      $interval = $this->dateFormatter->formatTimeDiffUntil($timestamp, $options);
      $display = new FormattableMarkup($timeago['future_format'], ['@interval' => $interval]);
      $summary[] = $this->t('Future date: %display', ['%display' => $display]);

      $timestamp = strtotime('-1 year -1 month -1 week -1 day -1 hour -1 minute');
      $interval = $this->dateFormatter->formatTimeDiffSince($timestamp, $options);
      $display = new FormattableMarkup($timeago['past_format'], ['@interval' => $interval]);
      $summary[] = $this->t('Past date: %display', ['%display' => $display]);

      if ($timeago['refresh']) {
        $refresh_intervals = $this->getRefreshIntervals();
        $summary[] = $this->t('Refresh in @interval', ['@interval' => $refresh_intervals[$timeago['refresh']]]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $date_format = $this->getSetting('date_format');
    $custom_date_format = '';
    $timezone = $this->getSetting('timezone') ?: NULL;
    $tooltip = $this->getSetting('tooltip');
    $langcode = $tooltip_langcode = NULL;
    $timeago = $this->getSetting('timeago');

    // If an RFC2822 date format is requested, then the month and day have to
    // be in English. @see http://www.faqs.org/rfcs/rfc2822.html
    if ($date_format === 'custom' && ($custom_date_format = $this->getSetting('custom_date_format')) === 'r') {
      $langcode = 'en';
    }
    if ($tooltip['date_format'] === 'custom' && $tooltip['custom_date_format'] === 'r') {
      $tooltip_langcode = 'en';
    }

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme' => 'time',
        '#attributes' => [
          // The representation of the date/time as RFC 3339 "date-time".
          // @see https://www.w3.org/TR/2011/WD-html-markup-20110405/time.html
          'datetime' => $this->dateFormatter->format($item->value, 'html_datetime', '', $timezone),
          // Show a tooltip on mouse hover as title. When the time is displayed
          // as 'time ago', it helps the user to read the exact date.
          'title' => $this->dateFormatter->format($item->value, $tooltip['date_format'], $tooltip['custom_date_format'], $timezone, $tooltip_langcode),
        ],
        '#text' => $this->dateFormatter->format($item->value, $date_format, $custom_date_format, $timezone, $langcode),
        '#cache' => [
          'contexts' => [
            'timezone',
          ],
        ],
      ];
      if ($timeago['enabled']) {
        $elements[$delta]['#attributes']['class'][] = 'js-timeago';
        $settings = [
          'format' => [
            'future' => $timeago['future_format'],
            'past' => $timeago['past_format'],
          ],
          'granularity' => $timeago['granularity'],
          'refresh' => $timeago['refresh'],
        ];
        $elements[$delta]['#attributes']['data-drupal-timeago'] = Json::encode($settings);
      }
    }

    if ($timeago['enabled']) {
      $elements['#attached']['library'][] = 'core/drupal.timeago';
    }

    return $elements;
  }

  /**
   * Builds the #states key for form elements.
   *
   * @param string[] $path
   *   The remote element path.
   * @param array $conditions
   *   The conditions to be checked.
   *
   * @return array[]
   *   The #states array.
   */
  protected function buildStates(array $path, array $conditions) {
    $path = '[' . implode('][', $path) . ']';
    return [
      'visible' => [
        [
          ":input[name='fields[{$this->fieldDefinition->getName()}][settings_edit_form][settings]$path']" => $conditions,
        ]
      ],
    ];
  }

  /**
   * Returns the refresh interval options for the jQuery 'time ago' display.
   *
   * @return array
   *   A list of refresh time intervals.
   */
  protected function getRefreshIntervals() {
    return [
      0 => $this->t('No refresh'),
      1 => $this->t('1 second'),
      10 => $this->t('10 seconds'),
      15 => $this->t('15 seconds'),
      30 => $this->t('30 seconds'),
      60 => $this->t('1 minute'),
      300 => $this->t('5 minutes'),
      600 => $this->t('10 minutes'),
    ];
  }

}

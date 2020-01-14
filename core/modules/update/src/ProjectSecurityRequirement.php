<?php

namespace Drupal\update;

use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Class for generating a project's security requirement.
 *
 * @see update_requirements()
 *
 * @internal
 *   This class implements logic to determine security coverage for Drupal core
 *   according to Drupal core security policy. It should not be extended or
 *   called directly.
 */
class ProjectSecurityRequirement {

  use StringTranslationTrait;

  /**
   * The next version after the installed version in the format [MAJOR].[MINOR].
   *
   * @var string
   */
  private $nextVersion;

  /**
   * The installed version in the format [MAJOR].[MINOR].
   *
   * @var string
   */
  private $existingVersion;

  /**
   * Drupal project data.
   *
   * @var array
   *
   * The following keys are used in this class:
   * - existing_version (string): The version of the project that is installed
   *   on the site.
   * - security_coverage_info (array): The security coverage information as
   *   returned by \Drupal\update\ProjectSecurityData::getCoverageInfo().
   * - project_type (string): The type of project.
   * - name (string): The project machine name.
   *
   * @see \Drupal\update\UpdateManagerInterface::getProjects()
   * @see \Drupal\update\ProjectSecurityData::getCoverageInfo()
   * @see update_process_project_info()
   */
  private $projectData;

  /**
   * Constructs a ProjectSecurityRequirement object.
   *
   * @param array $project_data
   *   Project data form Drupal\update\UpdateManagerInterface::getProjects().
   *   The 'security_coverage_info' key should be set by
   *   calling \Drupal\update\ProjectSecurityData::getCoverageInfo() before
   *   calling this method.
   */
  public function __construct(array $project_data) {
    $this->projectData = $project_data;
    if (isset($this->projectData['existing_version'])) {
      list($major, $minor) = explode('.', $this->projectData['existing_version']);
      $this->existingVersion = "$major.$minor";
      $this->nextVersion = "$major." . ((int) $minor + 1);
    }
  }

  /**
   * Gets the security coverage requirement if any.
   *
   * @return array
   *   Requirements array as specified by hook_requirements().
   */
  public function getRequirement() {
    if ($this->projectData['project_type'] !== 'core' || $this->projectData['name'] !== 'drupal') {
      return NULL;
    }
    if (isset($this->projectData['security_coverage_info']['support_end_version'])) {
      $requirement = $this->getVersionEndRequirement();
    }
    elseif (isset($this->projectData['security_coverage_info']['support_end_date'])) {
      $requirement = $this->getDateEndRequirement();
    }
    else {
      return NULL;
    }
    $requirement['title'] = $this->t('Drupal core security coverage');
    return $requirement;
  }

  /**
   * Get the requirements array based on support to a specific version.
   *
   * @return array
   *   Requirements array as specified by hook_requirements().
   */
  private function getVersionEndRequirement() {
    $requirement = [];
    $security_coverage_info = $this->projectData['security_coverage_info'];
    if ($security_coverage_message = $this->getVersionEndCoverageMessage()) {
      $requirement['description'] = $security_coverage_message;
      if ($security_coverage_info['additional_minors_coverage'] > 0) {
        $requirement['value'] = $this->t('Supported minor version');
        $requirement['severity'] = $security_coverage_info['additional_minors_coverage'] > 1 ? REQUIREMENT_INFO : REQUIREMENT_WARNING;
      }
      else {
        $requirement['value'] = $this->t('Unsupported minor version');
        $requirement['severity'] = REQUIREMENT_ERROR;
      }
    }
    return $requirement;
  }

  /**
   * Gets coverage message for additional minor version support.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The security coverage message, or an empty string if there is none.
   *
   * @see \Drupal\update\ProjectSecurityData::getCoverageInfo()
   */
  private function getVersionEndCoverageMessage() {
    $security_info = $this->projectData['security_coverage_info'];
    if ($security_info['additional_minors_coverage'] > 0) {
      // If the installed minor version will be supported until newer minor
      // versions are released inform the user.
      $translation_arguments = [
        '%project' => $this->projectData['title'],
        '%version' => $this->existingVersion,
        '%coverage_version' => $security_info['support_end_version'],
      ];
      $message = '<p>' . $this->t('The installed minor version of %project, %version, will stop receiving official security support after the release of %coverage_version.', $translation_arguments) . '</p>';

      if ($security_info['additional_minors_coverage'] === 1) {
        // If the installed minor version will only be supported for 1 newer
        // minor core version encourage the site owner to update soon.
        $message .= '<p>' . $this->t('Update to %next_minor or higher soon to continue receiving security updates.', ['%next_minor' => $this->nextVersion])
          . ' ' . static::getAvailableUpdatesMessage() . '</p>';
      }
    }
    else {
      // Because the current minor version is no longer supported advise the
      // site owner update.
      $message = $this->getVersionNotSupportedMessage();
    }
    if ($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal') {
      // Provide a link to the Drupal core documentation on release cycles
      // if the installed Drupal core minor is not supported.
      $message .= '<p>' . $this->t(
          'Visit the <a href=":url">release cycle overview</a> for more information on supported releases.',
          [
            ':url' => 'https://www.drupal.org/core/release-cycle-overview',
          ]
        ) . '</p>';
    }

    return Markup::create($message);
  }

  /**
   * Gets the security coverage requirement based on an end date.
   *
   * @return array
   *   Requirements array as specified by hook_requirements().
   */
  private function getDateEndRequirement() {
    $requirement = [];
    $security_info = $this->projectData['security_coverage_info'];
    $end_timestamp = \DateTime::createFromFormat('Y-m', $security_info['support_end_date'])->getTimestamp();
    /** @var \Drupal\Component\Datetime\Time $time */
    $time = \Drupal::service('datetime.time');
    $request_time = $time->getRequestTime();
    if ($end_timestamp <= $request_time) {
      // Support is over.
      $requirement['value'] = $this->t('Unsupported minor version');
      $requirement['severity'] = REQUIREMENT_ERROR;
      $requirement['description'] = $this->getVersionNotSupportedMessage();
    }
    else {
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
      $date_formatter = \Drupal::service('date.formatter');
      $requirement['value'] = $this->t('Supported minor version');
      $requirement['severity'] = REQUIREMENT_INFO;
      $translation_arguments = [
        '%project' => $this->projectData['title'],
        '%version' => $this->existingVersion,
        '%date' => $date_formatter->format($end_timestamp, 'custom', 'F Y'),
      ];
      $requirement['description'] = '<p>' . $this->t('The installed minor version of %project, %version, will stop receiving official security support after  %date.', $translation_arguments) . '</p>';
      if (isset($security_info['support_ending_warn_date']) && \DateTime::createFromFormat('Y-m', $security_info['support_ending_warn_date'])->getTimestamp() <= $request_time) {
        $requirement['description'] .= '<p>' . $this->t('Update to a supported minor version soon to continue receiving security updates.') . '</p>';
        $requirement['severity'] = REQUIREMENT_WARNING;
      }
    }
    if (isset($requirement['description'])) {
      $requirement['description'] = Markup::create($requirement['description']);
    }
    return $requirement;
  }

  /**
   * Gets the formatted message for an unsupported project.
   *
   * @return string
   *   The message for an unsupported version.
   */
  private function getVersionNotSupportedMessage() {
    $message = '<p>' . $this->t(
        'The installed minor version of %project, %version, is no longer supported and will not receive security updates.',
        [
          '%project' => $this->projectData['title'],
          '%version' => $this->existingVersion,
        ])
      . '</p><p>'
      . $this->t(
        'Update to a supported minor as soon as possible to continue receiving security updates.')
      . ' ' . static::getAvailableUpdatesMessage() . '</p>';
    return $message;
  }

  /**
   * Gets the message with a link to the available updates page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  private static function getAvailableUpdatesMessage() {
    return t(
      'See the <a href=":update_status_report">available updates</a> page for more information.',
      [':update_status_report' => Url::fromRoute('update.status')->toString()]
    );
  }

}

<?php

namespace Drupal\update;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\system\SystemManager;

/**
 * Class for generating a projects security requirement.
 *
 * @see update_requirements()
 *
 * @internal
 */
class ProjectSecurityRequirement {

  /**
   * The Drupal project data.
   *
   * @var array
   *
   * @see \Drupal\update\UpdateManagerInterface::getProjects()
   * @see update_process_project_info()
   */
  protected $projectData;

  /**
   * Constructs ProjectUpdateData object.
   *
   * @param array $project_data
   *   Project data form Drupal\update\UpdateManagerInterface::getProjects().
   *   The 'security_coverage_info' key should be set before using this class.
   *
   * @see \Drupal\update\ProjectSecurityCoverageCalculator::getSecurityCoverageInfo()
   */
  public function __construct(array $project_data) {
    $this->projectData = $project_data;
  }

  /**
   * Gets the security coverage requirement if any.
   *
   * @return array|null
   *   An array if there is security coverage requirement, otherwise NULL.
   */
  public function getSecurityCoverageRequirement() {
    if ($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal') {
      $requirement['title'] = t('Drupal core security coverage');
      if (!empty($this->projectData['security_coverage_info'])) {
        $security_coverage_info = $this->projectData['security_coverage_info'];
        if ($security_coverage_message = $this->getSecurityCoverageMessage()) {
          $requirement['description'] = $security_coverage_message;
          if ($security_coverage_info['additional_minors_coverage'] > 0) {
            $requirement['value'] = t('Supported minor version');
            $requirement['severity'] = $security_coverage_info['additional_minors_coverage'] > 1 ? REQUIREMENT_INFO : REQUIREMENT_WARNING;
          }
          else {
            $requirement['value'] = t('Unsupported minor version');
            $requirement['severity'] = REQUIREMENT_ERROR;
          }
          return $requirement;
        }
      }
      elseif ($lts_requirement = $this->getLtsRequirement()) {
        return $requirement + $lts_requirement;
      }
    }
    return NULL;
  }

  /**
   * Gets the security coverage message.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The security coverage message, or an empty string if there is none.
   *
   * @see \Drupal\update\ProjectSecurityCoverageCalculator::getSecurityCoverageInfo()
   */
  private function getSecurityCoverageMessage() {
    if (!isset($this->projectData['security_coverage_info']['additional_minors_coverage'])) {
      return '';
    }
    $security_info = $this->projectData['security_coverage_info'];
    list($major, $minor) = explode('.', $this->projectData['existing_version']);
    if ($security_info['additional_minors_coverage'] > 0) {
      // If the installed minor version will be supported until newer minor
      // versions are released inform the user.
      $message = '<p>' . t(
          'The installed minor version of %project, %version, will receive security updates until the release of %coverage_version.',
          [
            '%project' => $this->projectData['title'],
            '%version' => "$major.$minor",
            '%coverage_version' => $security_info['support_end_version'],
          ]
        ) . '</p>';

      if ($security_info['additional_minors_coverage'] === 1) {
        // If the installed minor version will only be supported for 1 newer
        // minor core version encourage the site owner to update soon.
        $message .= '<p>' . t(
            'Update to %next_minor or higher soon to continue receiving security updates.',
            [
              '%next_minor' => $this->projectData['existing_release']['version_major'] . '.' . ((int) $this->projectData['existing_release']['version_minor'] + 1),

            ]
          ) . ' ' . $this->getAvailableUpdatesMessage() . '</p>';
      }
    }
    else {
      // Because the current minor version is no longer supported advise the
      // site owner update.
      $message = $this->getVersionNotSupportedMessage("$major.$minor");
    }
    if ($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal') {
      // Provide a link to the Drupal core documentation on release cycles
      // if the installed Drupal core minor is not supported.
      $message .= '<p>' . t(
          'Visit the <a href=":url">release cycle overview</a> for more information on supported releases.',
          [
            ':url' => 'https://www.drupal.org/core/release-cycle-overview',
          ]
        ) . '</p>';
    }

    return Markup::create($message);
  }

  /**
   * Gets the security coverage requirement for LTS release.
   *
   * @return array
   *   An array if there is security coverage requirement, otherwise NULL.
   */
  private function getLtsRequirement() {
    if (!($this->projectData['project_type'] === 'core' && $this->projectData['name'] === 'drupal' && (int) $this->projectData['existing_major'] === 8)) {
      return [];
    }
    $minor_version = explode('.', $this->projectData['existing_version'])[1];
    $requirement = [];
    if ($minor_version === '8') {
      $requirement = $this->createRequirementForSupportEndDate('2020-12-02', '6 months');
    }
    elseif ($minor_version === '9') {
      $requirement = $this->createRequirementForSupportEndDate('2021-11-01');
    }
    return $requirement;
  }

  /**
   * Gets the formatted message for an unsupported project.
   *
   * @param string $minor_version
   *   The installed minor version.
   *
   * @return string
   *   The message for an unsupported version.
   */
  private function getVersionNotSupportedMessage($minor_version) {
    $message = '<p>' . t(
        'The installed minor version of %project, %version, is no longer supported and will not receive security updates.',
        [
          '%project' => $this->projectData['name'],
          '%version' => $minor_version,
        ])
      . '</p><p>'
      . t(
        'Update to a supported minor as soon as possible to continue receiving security updates.')
      . ' ' . $this->getAvailableUpdatesMessage() . '</p>';
    return $message;
  }

  /**
   * Gets the message with a link to the available updates page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  private function getAvailableUpdatesMessage() {
    return t(
      'See the <a href=":update_status_report">available updates</a> page for more information.',
      [':update_status_report' => Url::fromRoute('update.status')->toString()]
    );
  }

  /**
   * Creates a requirements array for a project version with a support end date.
   *
   * @param string $end_date_string
   *   The date date the support will end in the format 'YYYY-MM-DD'.
   * @param string $warn_at
   *   The time before support ends to add an update warning. This a date part
   *   string that can be used in \DateInterval::createFromDateString().
   *
   * @return array
   *   A requirements array as used in hook_requirements().
   */
  private function createRequirementForSupportEndDate($end_date_string, $warn_at = '') {
    list(, $minor_version) = explode('.', $this->projectData['existing_version']);
    $current_minor = "{$this->projectData['existing_major']}.$minor_version";
    $end_date = \DateTime::createFromFormat('Y-m-d', $end_date_string);
    $end_timestamp = $end_date->getTimestamp();
    /** @var \Drupal\Component\Datetime\Time $time */
    $time = \Drupal::service('datetime.time');
    $request_time = $time->getRequestTime();
    if ($end_timestamp <= $request_time) {
      // LTS support is over.
      $requirement['value'] = t('Unsupported minor version');
      $requirement['severity'] = SystemManager::REQUIREMENT_ERROR;
      $requirement['description'] = $this->getVersionNotSupportedMessage($current_minor);
    }
    else {
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
      $date_formatter = \Drupal::service('date.formatter');
      $requirement['value'] = t('Supported minor version');
      $requirement['severity'] = SystemManager::REQUIREMENT_WARNING;
      $requirement['description'] = '<p>' . t(
          'The installed minor version of %project, %version, will receive security updates until %date.',
          [
            '%project' => $this->projectData['name'],
            '%version' => $current_minor,
            '%date' => $date_formatter->format($end_timestamp, 'html_date'),
          ]
        ) . '</p>';
      if ($warn_at && $end_date->sub(\DateInterval::createFromDateString($warn_at))->getTimestamp() <= $request_time) {
        $requirement['description'] .= '<p>' . t('Update to a supported minor version soon to continue receiving security updates.') . '</p>';
      }
    }
    if (isset($requirement['description'])) {
      $requirement['description'] = Markup::create($requirement['description']);
    }
    return $requirement;
  }

}

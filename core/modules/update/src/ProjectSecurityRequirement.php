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
 *   according to Drupal core security policy. It should not be called directly.
 */
final class ProjectSecurityRequirement {

  use StringTranslationTrait;

  /**
   * The project title.
   *
   * @var string|null
   */
  protected $projectTitle;

  /**
   * Security coverage information for the project.
   *
   * @see \Drupal\update\ProjectSecurityData::getCoverageInfo().
   *
   * @var array
   */
  private $securityCoverageInfo;

  /**
   * The next version after the installed version in the format [MAJOR].[MINOR].
   *
   * @var string|null
   */
  private $nextVersion;

  /**
   * The installed version in the format [MAJOR].[MINOR].
   *
   * @var string|null
   */
  private $existingVersion;

  /**
   * Constructs a ProjectSecurityRequirement object.
   *
   * @param string|null $project_title
   *   The project title.
   * @param array $security_coverage_info
   *   Security coverage information as set
   * @param string|null $existing_version
   *   The next version after the installed version in the format
   *   [MAJOR].[MINOR].
   * @param string|null $next_version
   *   The next version after the installed version in the format
   *   [MAJOR].[MINOR].
   */
  private function __construct($project_title = NULL, array $security_coverage_info = [], $existing_version = NULL, $next_version = NULL) {
    $this->projectTitle = $project_title;
    $this->securityCoverageInfo = $security_coverage_info;
    $this->existingVersion = $existing_version;
    $this->nextVersion = $next_version;
  }

  /**
   * Constructs a ProjectSecurityRequirement object from project data.
   *
   * @param array $project_data
   *   Project data form Drupal\update\UpdateManagerInterface::getProjects().
   *   The 'security_coverage_info' key should be set by
   *   calling \Drupal\update\ProjectSecurityData::getCoverageInfo() before
   *   calling this method. The following keys are used in this method:
   *   - existing_version (string): The version of the project that is installed
   *     on the site.
   *   - security_coverage_info (array): The security coverage information as
   *     returned by \Drupal\update\ProjectSecurityData::getCoverageInfo().
   *   - project_type (string): The type of project.
   *   - name (string): The project machine name.
   *   - title (string): The project title.
   *
   * @return \Drupal\update\ProjectSecurityRequirement
   *   The ProjectSecurityRequirement instance.
   *
   * @see \Drupal\update\UpdateManagerInterface::getProjects()
   * @see \Drupal\update\ProjectSecurityData::getCoverageInfo()
   * @see update_process_project_info()
   */
  public static function createFromProjectDataArray(array $project_data) {
    if ($project_data['project_type'] !== 'core' || $project_data['name'] !== 'drupal' || empty($project_data['security_coverage_info'])) {
      return new static();
    }
    if (isset($project_data['existing_version'])) {
      list($major, $minor) = explode('.', $project_data['existing_version']);
      $existing_version = "$major.$minor";
      $next_version = "$major." . ((int) $minor + 1);
      return new static($project_data['title'], $project_data['security_coverage_info'], $existing_version, $next_version);
    }
    return new static($project_data['title'], $project_data['security_coverage_info']);
  }

  /**
   * Gets the security coverage requirement if any.
   *
   * @return array
   *   Requirements array as specified by hook_requirements() or an empty array
   *   if no requirements can be determined.
   */
  public function getRequirement() {
    if (isset($this->securityCoverageInfo['support_end_version'])) {
      $requirement = $this->getVersionEndRequirement();
    }
    elseif (isset($this->securityCoverageInfo['support_end_date'])) {
      $requirement = $this->getDateEndRequirement();
    }
    else {
      return [];
    }
    $requirement['title'] = $this->t('Drupal core security coverage');
    return $requirement;
  }

  /**
   * Gets the requirements array based on support to a specific version.
   *
   * @return array
   *   Requirements array as specified by hook_requirements().
   */
  private function getVersionEndRequirement() {
    $requirement = [];
    if ($security_coverage_message = $this->getVersionEndCoverageMessage()) {
      $requirement['description'] = $security_coverage_message;
      if ($this->securityCoverageInfo['additional_minors_coverage'] > 0) {
        $requirement['value'] = $this->t('Supported minor version');
        $requirement['severity'] = $this->securityCoverageInfo['additional_minors_coverage'] > 1 ? REQUIREMENT_INFO : REQUIREMENT_WARNING;
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
    if ($this->securityCoverageInfo['additional_minors_coverage'] > 0) {
      // If the installed minor version will be supported until newer minor
      // versions are released inform the user.
      $translation_arguments = [
        '@project' => $this->projectTitle,
        '@version' => $this->existingVersion,
        '@coverage_version' => $this->securityCoverageInfo['support_end_version'],
      ];
      $message = '<p>' . $this->t('The installed minor version of @project (@version), will stop receiving official security support after the release of @coverage_version.', $translation_arguments) . '</p>';

      if ($this->securityCoverageInfo['additional_minors_coverage'] === 1) {
        // If the installed minor version will only be supported for 1 newer
        // minor core version encourage the site owner to update soon.
        $message .= '<p>' . $this->t('Update to @next_minor or higher soon to continue receiving security updates.', ['@next_minor' => $this->nextVersion])
          . ' ' . static::getAvailableUpdatesMessage() . '</p>';
      }
    }
    else {
      // Because the current minor version is no longer supported advise the
      // site owner update.
      $message = $this->getVersionNotSupportedMessage();
    }
    $message .= $this->getReleaseCycleLink();

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
    /** @var \Drupal\Component\Datetime\Time $time */
    $time = \Drupal::service('datetime.time');
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    // 'support_end_date' will either be in format 'Y-m-d' or 'Y-m'.
    if (substr_count($this->securityCoverageInfo['support_end_date'], '-') === 2) {
      $date_format = 'Y-m-d';
      $full_support_end_date = $this->securityCoverageInfo['support_end_date'];
    }
    else {
      $date_format = 'Y-m';
      // If the date does not include a day use '15'. When calling
      // \DateTime::createFromFormat() the current day will be used if one is
      // not provided. This may cause the month to be wrong at the beginning or
      // end of the month. '15' will never be displayed because we are using the
      // 'Y-m' format.
      $full_support_end_date = $this->securityCoverageInfo['support_end_date'] . '-15';
    }
    $support_end_timestamp = \DateTime::createFromFormat('Y-m-d', $full_support_end_date)->getTimestamp();
    $formatted_end_date = $date_format === 'Y-m-d' ? $this->securityCoverageInfo['support_end_date'] : $date_formatter->format($support_end_timestamp, 'custom', 'F Y');
    $comparable_request_date = $date_formatter->format($time->getRequestTime(), 'custom', $date_format);
    if ($this->securityCoverageInfo['support_end_date'] <= $comparable_request_date) {
      // Support is over.
      $requirement['value'] = $this->t('Unsupported minor version');
      $requirement['severity'] = REQUIREMENT_ERROR;
      $requirement['description'] = $this->getVersionNotSupportedMessage();
    }
    else {
      $requirement['value'] = $this->t('Supported minor version');
      $requirement['severity'] = REQUIREMENT_INFO;
      $translation_arguments = [
        '@project' => $this->projectTitle,
        '@version' => $this->existingVersion,
        '@date' => $formatted_end_date,
      ];
      $requirement['description'] = '<p>' . $this->t('The installed minor version of @project (@version), will stop receiving official security support after @date.', $translation_arguments) . '</p>';
      // 'support_ending_warn_date' will always be in the format 'Y-m-d'.
      $request_date = $date_formatter->format($time->getRequestTime(), 'custom', 'Y-m-d');
      if (!empty($this->securityCoverageInfo['support_ending_warn_date']) && $this->securityCoverageInfo['support_ending_warn_date'] <= $request_date) {
        $requirement['description'] .= '<p>' . $this->t('Update to a supported minor version soon to continue receiving security updates.') . '</p>';
        $requirement['severity'] = REQUIREMENT_WARNING;
      }
    }
    $requirement['description'] = Markup::create($requirement['description'] . $this->getReleaseCycleLink());
    return $requirement;
  }

  /**
   * Gets the formatted message for an unsupported project.
   *
   * @return string
   *   The message for an unsupported version.
   */
  private function getVersionNotSupportedMessage() {
    return '<p>' . $this->t(
        'The installed minor version of @project (@version), is no longer supported and will not receive security updates.',
        [
          '@project' => $this->projectTitle,
          '@version' => $this->existingVersion,
        ])
      . '</p><p>'
      . $this->t('Update to a supported minor as soon as possible to continue receiving security updates.')
      . ' ' . static::getAvailableUpdatesMessage() . '</p>';
  }

  /**
   * Gets the message with a link to the available updates page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  private function getAvailableUpdatesMessage() {
    return $this->t(
      'See the <a href=":update_status_report">available updates</a> page for more information.',
      [':update_status_report' => Url::fromRoute('update.status')->toString()]
    );
  }

  /**
   * Gets a link the release cycle page on drupal.org.
   *
   * @return string
   *   A link to the release cycle page on drupal.org.
   */
  private function getReleaseCycleLink() {
    return '<p>' . $this->t(
        'Visit the <a href=":url">release cycle overview</a> for more information on supported releases.',
        [':url' => 'https://www.drupal.org/core/release-cycle-overview']
      ) . '</p>';
  }

}

<?php

namespace Drupal\update;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\system\SystemManager;

/**
 * Update helper methods.
 *
 * @internal
 */
class UpdateHelper {

  /**
   * The number of minor versions of Drupal core that are supported.
   */
  const CORE_MINORS_SUPPORTED = 2;

  /**
   * Gets the security coverage information for a project.
   *
   * Currently only Drupal core is supported.
   *
   * @param array $project_data
   *   The project data.
   * @param array $releases
   *   Releases as returned by update_get_available().
   *
   * @return array
   *   The security coverage information.
   */
  public static function getSecurityCoverageInfo(array $project_data, array $releases) {
    $info = [];
    if (!($project_data['project_type'] === 'core' && $project_data['name'] === 'drupal')) {
      // Only Drupal core has an explicit coverage range.
      return [];
    }
    if ($lts_requirement = static::getLtsRequirement($project_data, $releases)) {
      $info['lts_requirement'] = $lts_requirement;
    }
    else if ($support_until_release = static::getSupportUntilReleaseInfo($project_data, $releases)) {
      $info['supported_until'] = $support_until_release;
      if (static::isNextMajorReleasedWithoutSupportedReleased($releases, $support_until_release)) {
        // If the next major version has been released but
        // $support_until_release has not we cannot know the coverage status.
        return [];
      }
      $info['additional_minors_coverage'] = static::getAdditionalSecuritySupportedMinors($support_until_release, $releases);
    }

    return $info;
  }

  /**
   * Gets the number of additional minor releases supported.
   *
   * @param array $security_supported_release_info
   *   The security supported release.
   * @param array $releases
   *   Releases as returned by update_get_available().
   *
   * @return int
   *   The number of additional supported minor releases.
   *
   * @throws \Exception
   */
  private static function getAdditionalSecuritySupportedMinors(array $security_supported_release_info, array $releases) {
    $latest_full_release = static::getMostRecentFullRelease($releases);
    if ((int) $latest_full_release['version_major'] > (int) $security_supported_release_info['version_major']) {
      // Even if there is new major version we can know if the installed version
      // is not supported because the version it is supported till has already
      // been released.
      if ($latest_full_release = static::getMostRecentFullRelease($releases, $security_supported_release_info['version_major'])) {
        if ((int) $security_supported_release_info['version_minor'] <= (int) $latest_full_release['version_minor']) {
          return -1;
        }
        else {
          throw new \LogicException('::getAdditionalSecuritySupportedMinors() should never bee called before checking ::isNextMajorReleasedWithoutSupportedReleased().');
        }
      }
    }
    elseif ((int) $latest_full_release['version_major'] === (int) $security_supported_release_info['version_major']) {
      return (int) $security_supported_release_info['version_minor'] - (int) $latest_full_release['version_minor'];
    }
    // The latest full release was a lower major version.
    return -1;
  }

  /**
   * Gets information about the release the current minor is supported until.
   *
   * @todo In https://www.drupal.org/node/2608062 determine how we will know
   *    what the final minor release of a particular major version will be. This
   *    method should not return a version beyond that minor.
   *
   * @param array $project_data
   *   The project data.
   * @param array $releases
   *   Releases as returned by update_get_available().
   *
   * @return array
   *   The release information.
   */
  private static function getSupportUntilReleaseInfo(array $project_data, array $releases) {
    if (empty($releases[$project_data['existing_version']])) {
      return [];
    }
    $existing_release = $releases[$project_data['existing_version']];

    if (!empty($existing_release['version_extra'])) {
      return [];
    }
    $support_until_release = [
      'version_major' => (int) $existing_release['version_major'],
      'version_minor' => ((int) $existing_release['version_minor']) + static::CORE_MINORS_SUPPORTED,
      'version_patch' => 0,
    ];
    $support_until_release['version'] = implode('.', $support_until_release);
    return $support_until_release;
  }

  /**
   * Gets the most recent full release.
   *
   * @param array $releases
   *   Releases as returned by update_get_available().
   * @param string|null $major
   *   (optional) Version major.
   *
   * @return array|null
   *   The most recent full release if found, otherwise NULL.
   */
  private static function getMostRecentFullRelease(array $releases, $major = NULL) {
    foreach ($releases as $release) {
      if ($major && (int) $release['version_major'] !== (int) $major) {
        continue;
      }
      if ($release['status'] === 'published' && empty($release['version_extra'])) {
        return $release;
      }
    }
    return NULL;
  }

  /**
   * Gets the security coverage message.
   *
   * @param array $project_data
   *   The project data.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The security coverage message, or an empty string if there is none.
   */
  private static function getSecurityCoverageMessage(array $project_data) {
    if (!isset($project_data['security_coverage_info']['additional_minors_coverage'])) {
      return '';
    }
    $security_info = $project_data['security_coverage_info'];
    list($major, $minor) = explode('.', $project_data['existing_version']);
    if ($security_info['additional_minors_coverage'] > 0) {
      // If the installed minor version will be supported until newer minor
      // versions are released inform the user.
      $support_until_release = $security_info['supported_until'];
      $message = static::getVersionSupportedMessage($project_data['title'],  "$major.$minor", $support_until_release['version'], $project_data['existing_release'], $security_info['additional_minors_coverage']);
    }
    else {
      // Because the current minor version is no longer supported advise the
      // site owner update.
      $message = static::getVersionNotSupportedMessage($project_data['title'], "$major.$minor");
    }
    if ($project_data['project_type'] === 'core' && $project_data['name'] === 'drupal') {
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
   * Gets the security coverage requirement if any.
   *
   * @param array $project_data
   *   The project data.
   *
   * @return array|null
   *   An array if there is security coverage requirement, otherwise NULL.
   */
  public static function getSecurityCoverageRequirement(array $project_data) {
    if ($project_data['project_type'] == 'core' && !empty($project_data['security_coverage_info'])) {
      $requirement['title'] = t('Drupal core security coverage');
      $security_coverage_info = $project_data['security_coverage_info'];
      if ($security_coverage_message = static::getSecurityCoverageMessage($project_data)) {
        $requirement['description'] = $security_coverage_message;
        if ($security_coverage_info['additional_minors_coverage'] > 0) {
          $requirement['value'] = t('Supported minor version');
          $requirement['severity'] = REQUIREMENT_INFO;
          if ($security_coverage_info['additional_minors_coverage'] > 1) {
            $requirement['severity'] = REQUIREMENT_INFO;
          }
          else {
            $requirement['severity'] = REQUIREMENT_WARNING;
          }
        }
        else {
          $requirement['value'] = t('Unsupported minor version');
          $requirement['severity'] = REQUIREMENT_ERROR;
        }
        return $requirement;
      }
      elseif (isset($project_data['security_coverage_info']['lts_requirement'])) {
        return  $requirement + $project_data['security_coverage_info']['lts_requirement'];
      }
    }
    return NULL;
  }

  /**
   * Determines if next major version is available and supported release is not.
   *
   * If the next major version is released but the version that the currently
   * installed version is supported till is not released then we cannot
   * determine if the currently installed version is within the support window.
   *
   * @param array $releases
   *   Releases as returned by update_get_available().
   * @param array $security_supported_release_info
   *   Release information as return by update_get_available().
   *
   * @return bool
   *   TRUE if the next major version has been released and the supported until
   *   release is not available.
   */
  private static function isNextMajorReleasedWithoutSupportedReleased(array $releases, array $security_supported_release_info) {
    $latest_full_release = static::getMostRecentFullRelease($releases);
    if ((int) $latest_full_release['version_major'] > (int) $security_supported_release_info['version_major']) {
      // Even if there is new major version we can know if the installed version
      // is not supported because the version it is supported till has already
      // been released.
      $latest_full_release = static::getMostRecentFullRelease($releases, $security_supported_release_info['version_major']);
      if ((int) $security_supported_release_info['version_minor'] > (int) $latest_full_release['version_minor']) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @param array $project_data
   *   The project data.
   * @param array $releases
   *   Releases as returned by update_get_available().
   *
   * @return array
   *   The security coverage information.
   */
  private static function getLtsRequirement(array $project_data, array $releases) {
    if (!($project_data['project_type'] === 'core' && $project_data['name'] === 'drupal' && (int) $project_data['existing_major'] === 8)) {
      return [];
    }
    list(,$minor_version) = explode('.', $project_data['existing_version']);
    $minor_version = (int) $minor_version;
    $requirement = [];
    if ($minor_version === 8) {
      $requirement = static::createRequirementForSupportEndDate($project_data, '12/02/2020');
    }
    if ($minor_version === 9) {
      $requirement = static::createRequirementForSupportEndDate($project_data, '11/01/2021');
    }
    if (isset($requirement['description'])) {
      $requirement['description'] = Markup::create($requirement['description']);
    }
    return $requirement;
  }

  /**
   * @param string $project
   * @param string $version
   *
   * @return string
   */
  private static function getVersionNotSupportedMessage($project, $version) {
    $message = '<p>' . t(
        'The installed minor version of %project, %version, is no longer supported and will not receive security updates.',
        [
          '%project' => $project,
          '%version' => $version,
        ])
      . '</p><p>'
      . t(
        'Update to a supported minor as soon as possible to continue receiving security updates.')
      . ' ' . static::getAvailableUpdatesMessage() . '</p>';
    return $message;
  }

  /**
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  private static function getAvailableUpdatesMessage() {
    return t(
      'See the <a href=":update_status_report">available updates</a> page for more information.',
      [':update_status_report' => Url::fromRoute('update.status')->toString()]
    );
  }

  private static function getVersionSupportedMessage($title, $current_minor, $supported_until_version, $existing_release, $additional_minors_coverage) {
    $message = '<p>' . t(
        'The installed minor version of %project, %version, will receive security updates until the release of %coverage_version.',
        [
          '%project' => $title,
          '%version' =>$current_minor,
          '%coverage_version' => $supported_until_version,
        ]
      ) . '</p>';

    if ($additional_minors_coverage === 1) {
      // If the installed minor version will only be supported for 1 newer
      // minor core version encourage the site owner to update soon.
      $message .= '<p>' . t(
          'Update to %next_minor or higher soon to continue receiving security updates.',
          [
            '%next_minor' => $existing_release['version_major'] . '.' . ((int) $existing_release + 1),

          ]
        ) . ' ' . static::getAvailableUpdatesMessage() . '</p>';
    }
    return $message;
  }

  /**
   * @param string $title
   * @param string $current_minor
   * @param int $timestamp
   *
   * @return string
   */
  private static function getSupportedUntilDateMessage($title, $current_minor, $timestamp) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    return '<p>' . t(
        'The installed minor version of %project, %version, will receive security updates until %date.',
        [
          '%project' => $title,
          '%version' => $current_minor,
          '%date' => $date_formatter->format($timestamp, 'html_date'),
        ]
      ) . '</p>';
  }

  /**
   * @param array $project_data
   * @param array $requirement
   * @param $current_minor
   *
   * @return array
   */
  private static function createRequirementForSupportEndDate(array $project_data, $end_date_string) {
    list(,$minor_version) = explode('.', $project_data['existing_version']);
    $minor_version = (int) $minor_version;
    $requirement = [];
    $current_minor = "{$project_data['existing_major']}.$minor_version";
    $end_timestamp = \DateTime::createFromFormat('m/d/Y', $end_date_string)->getTimestamp();
    /** @var \Drupal\Component\Datetime\Time $time */
    $time = \Drupal::service('datetime.time');
    if ($end_timestamp <= $time->getRequestTime()) {
      // LTS support is over.
      $requirement['value'] = t('Unsupported minor version');
      $requirement['severity'] = SystemManager::REQUIREMENT_ERROR;
      $requirement['description'] = static::getVersionNotSupportedMessage($project_data['title'], $current_minor);
    }
    else {
      $requirement['value'] = t('Supported minor version');
      $requirement['severity'] = SystemManager::REQUIREMENT_WARNING;
      $requirement['description'] = static::getSupportedUntilDateMessage($project_data['title'], $current_minor, $end_timestamp);
    }
    return $requirement;
  }

}

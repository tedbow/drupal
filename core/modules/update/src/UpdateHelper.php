<?php

namespace Drupal\update;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

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
      return $info;
    }
    if ($support_until_release = static::getSupportUntilReleaseInfo($project_data, $releases)) {
      $info['supported_until'] = $support_until_release;
      if (static::isNextMajorReleasedWithoutSupportedReleased($releases, $support_until_release)) {
        // If the next major version has been released but
        // $support_until_release has not we cannot know the coverage status.
        return [];
      }
      $info['additional_minors_coverage'] = static::getAdditionalSecuritySupportedMinors($support_until_release, $releases);
    }
    if ($existing_release = static::getExistingRelease($project_data, $releases)) {
      if ($lts_release = static::getMajorLts($project_data, $existing_release['version_major'])) {
        if ((int) $lts_release['version_minor'] === (int) $existing_release['version_minor']) {
          $info['current_lts_release'] = $lts_release;
        }
      }
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
    else {
      // The latest full release was a lower major version.
      return static::CORE_MINORS_SUPPORTED;
    }

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
    $existing_release = static::getExistingRelease($project_data, $releases);

    if (empty($existing_release) || !empty($existing_release['version_extra'])) {
      return [];
    }
    $support_until_release = [
      'version_major' => (int) $existing_release['version_major'],
      'version_minor' => ((int) $existing_release['version_minor']) + static::CORE_MINORS_SUPPORTED,
      'version_patch' => 0,
    ];
    $support_until_release['version'] = implode('.', $support_until_release);
    if ($lts_release = static::getMajorLts($project_data, $existing_release['version_major'])) {
      if ($lts_release['version_minor'] - (int) $existing_release['version_minor'] === 1) {
        // If the current release is 1 minor before the LTS release then this
        // version will be supported until the next major is released.
        $next_major = ((int) $existing_release['version_major']) + 1;
        $support_until_release = [
          'version_major' => $next_major,
          'version_minor' => 0,
          'version_patch' => 0,
          'version' => "$next_major.0.0",
        ];
      }
      elseif (version_compare($support_until_release['version'], $lts_release['version'], '>')) {
        $support_until_release = $lts_release;
      }
    }

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
      file_put_contents('/Users/ted.bowman/Sites/www/kg.txt', print_r($project_data, TRUE));
    if (!isset($project_data['security_coverage_info']['additional_minors_coverage'])) {
      return '';
    }
    $security_info = $project_data['security_coverage_info'];
    $available_updates_message = t('See the <a href=":update_status_report">available updates</a> page for more information.', [':update_status_report' => Url::fromRoute('update.status')->toString()]);
    list($major, $minor) = explode('.', $project_data['existing_version']);
    if (!empty($security_info['current_lts_release'])) {
      $message = t(
        'The installed minor version of %project, %version, is a Long Term Service release and will be supported until %support_end.',
        [
          '%project' => $project_data['title'],
          '%version' => "$major.$minor",
          '%support_end' => $security_info['current_lts_release']['support_end'],
        ]
      );
    }
    elseif ($security_info['additional_minors_coverage'] > 0) {
      // If the installed minor version will be supported until newer minor
      // versions are released inform the user.
      $support_until_release = $security_info['supported_until'];
      $message = '<p>' . t(
        'The installed minor version of %project, %version, will receive security updates until the release of %coverage_version.',
        [
          '%project' => $project_data['title'],
          '%version' => "$major.$minor",
          '%coverage_version' => $support_until_release['version'],
        ]
      ) . '</p>';

      if ($security_info['additional_minors_coverage'] === 1) {
        // If the installed minor version will only be supported for 1 newer
        // minor core version encourage the site owner to update soon.
        $message .= '<p>' . t(
            'Update to %next_minor or higher soon to continue receiving security updates.',
            [
              '%next_minor' => $project_data['existing_release']['version_major'] . '.' . ((int) $project_data['existing_release']['version_minor'] + 1),

            ]
          ) . ' ' . $available_updates_message . '</p>';
      }
    }
    else {
      // Because the current minor version is no longer supported advise the
      // site owner update.
      $message = '<p>' . t(
        'The installed minor version of %project, %version, is no longer supported and will not receive security updates.',
        [
          '%project' => $project_data['title'],
          '%version' => "$major.$minor",
        ])
        . '</p><p>'
        . t(
          'Update to a supported minor as soon as possible to continue receiving security updates.')
        . ' ' . $available_updates_message . '</p>';
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
        if ($security_coverage_message = static::getSecurityCoverageMessage($project_data)) {
        $requirement['title'] = t('Drupal core security coverage');
        $requirement['description'] = $security_coverage_message;
        if (!empty($project_data['security_coverage_info']['current_lts_release'])) {
          // @todo Check date
          $requirement['value'] = t('Long Term Service version');
          $requirement['severity'] = REQUIREMENT_INFO;
        }
        elseif ($project_data['security_coverage_info']['additional_minors_coverage'] > 0) {
          $requirement['value'] = t('Supported minor version');
          $requirement['severity'] = REQUIREMENT_INFO;
          if ($project_data['security_coverage_info']['additional_minors_coverage'] > 1) {
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
    }
    file_put_contents('/Users/ted.bowman/Sites/www/ffff.txt', print_r($project_data, TRUE));
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

  public static function setFallBackLts(array &$available) {
    $project_lts_releases = [
      'drupal' => [
        [
          'version_major' => 8,
          'version_minor' => 7,
          'version_patch' => 0,
          'version' => '8.9.0',
          'support_end' => '11/01/21',
        ],
      ],
    ];

    foreach ($project_lts_releases as $project_name => $lts_releases) {
      if (isset($available[$project_name])) {
        foreach ($lts_releases as $fallback_lts_release) {
          $existing_major_lts_release = static::getMajorLts($available[$project_name], $fallback_lts_release['version_major']);
          if (empty($existing_major_lts_release)) {
            $available[$project_name]['lts_releases'][] = $fallback_lts_release;
          }

        }
      }
    }
  }

  /**
   * Gets the LTS release information for a project major version if any.
   *
   * @param array $project_data
   *   The project data.
   * @param int $major_version
   *   The major version to check.
   *
   * @return array
   *   The LTS release information.
   */
  private static function getMajorLts(array $project_data, $major_version) {
    if (empty($project_data['lts_releases'])) {
      return NULL;
    }
    foreach ($project_data['lts_releases'] as $lts_release) {

      // There should only be one LTS version per major version.
      if ((int) $major_version === (int) $lts_release['version_major']) {
        return $lts_release;
      }
    }
    return NULL;
  }

  /**
   * Gets existing release if any.
   *
   * @param array $project_data
   *   The project data.
   * @param array $releases
   *   Releases as returned by update_get_available().
   *
   * @return array
   *   The release information.
   */
  private static function getExistingRelease(array $project_data, array $releases) {
    if (empty($releases[$project_data['existing_version']])) {
      return [];
    }
    return $releases[$project_data['existing_version']];
  }

}

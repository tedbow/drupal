<?php

/**
 * @file
 * Code required only when comparing available updates to existing data.
 */

use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateManagerInterface;
use Drupal\update\ModuleVersion;
use Drupal\update\ProjectCoreCompatibility;
use Drupal\update\UpdateProjectStatus;

/**
 * Determines version and type information for currently installed projects.
 *
 * Processes the list of projects on the system to figure out the currently
 * installed versions, and other information that is required before we can
 * compare against the available releases to produce the status report.
 *
 * @param $projects
 *   Array of project information from
 *   \Drupal\update\UpdateManager::getProjects().
 */
function update_process_project_info(&$projects) {
  foreach ($projects as $key => $project) {
    // Assume an official release until we see otherwise.
    $install_type = 'official';

    $info = $project['info'];

    if (isset($info['version'])) {
      // Check for development snapshots
      if (preg_match('@(dev|HEAD)@', $info['version'])) {
        $install_type = 'dev';
      }

      // Figure out what the currently installed major version is. We need
      // to handle both contribution (e.g. "5.x-1.3", major = 1) and core
      // (e.g. "5.1", major = 5) version strings.
      $matches = [];
      if (preg_match('/^(\d+\.x-)?(\d+)\..*$/', $info['version'], $matches)) {
        $info['major'] = $matches[2];
      }
      elseif (!isset($info['major'])) {
        // This would only happen for version strings that don't follow the
        // drupal.org convention. We let contribs define "major" in their
        // .info.yml in this case, and only if that's missing would we hit this.
        $info['major'] = -1;
      }
    }
    else {
      // No version info available at all.
      $install_type = 'unknown';
      $info['version'] = t('Unknown');
      $info['major'] = -1;
    }

    // Finally, save the results we care about into the $projects array.
    $projects[$key]['existing_version'] = $info['version'];
    $projects[$key]['existing_major'] = $info['major'];
    $projects[$key]['install_type'] = $install_type;
  }
}

/**
 * Calculates the current update status of all projects on the site.
 *
 * The results of this function are expensive to compute, especially on sites
 * with lots of modules or themes, since it involves a lot of comparisons and
 * other operations. Therefore, we store the results. However, since this is not
 * the data about available updates fetched from the network, it is ok to
 * invalidate it somewhat quickly. If we keep this data for very long, site
 * administrators are more likely to see incorrect results if they upgrade to a
 * newer version of a module or theme but do not visit certain pages that
 * automatically clear this.
 *
 * @param array $available
 *   Data about available project releases.
 *
 * @return
 *   An array of installed projects with current update status information.
 *
 * @see update_get_available()
 * @see \Drupal\update\UpdateManager::getProjects()
 * @see update_process_project_info()
 * @see \Drupal\update\UpdateManagerInterface::projectStorage()
 * @see \Drupal\update\ProjectCoreCompatibility::setReleaseMessage()
 */
function update_calculate_project_data($available) {
  // Retrieve the projects from storage, if present.
  $projects = \Drupal::service('update.manager')->projectStorage('update_project_data');
  // If $projects is empty, then the data must be rebuilt.
  // Otherwise, return the data and skip the rest of the function.
  if (!empty($projects)) {
    return $projects;
  }
  $projects = \Drupal::service('update.manager')->getProjects();
  update_process_project_info($projects);
  if (isset($projects['drupal']) && !empty($available['drupal'])) {
    // Calculate core status first so that it is complete before
    // \Drupal\update\ProjectCoreCompatibility::setReleaseMessage() is called
    // for each module below.
    $projects['drupal'] = (new UpdateProjectStatus($projects['drupal'], $available['drupal']))->getUpdateProjectData();
    if (isset($available['drupal']['releases'])) {
      $project_core_compatibility = new ProjectCoreCompatibility($projects['drupal'], $available['drupal']['releases']);
    }
  }

  foreach ($projects as $project => $project_info) {
    if (isset($available[$project])) {
      if ($project === 'drupal') {
        continue;
      }
      $projects[$project] = (new UpdateProjectStatus($projects[$project], $available[$project]))->getUpdateProjectData();
      // Inject the list of compatible core versions to show administrator(s)
      // which versions of core a given available update can be installed with.
      // Since individual releases of a project can be compatible with different
      // versions of core, and even multiple major versions of core (for
      // example, 8.9.x and 9.0.x), this list will hopefully help
      // administrator(s) know which available updates they can upgrade a given
      // project to.
      if (isset($project_core_compatibility)) {
        $project_core_compatibility->setReleaseMessage($projects[$project]);
      }
    }
    else {
      $projects[$project]['status'] = UpdateFetcherInterface::UNKNOWN;
      $projects[$project]['reason'] = t('No available releases found');
    }
  }
  // Give other modules a chance to alter the status (for example, to allow a
  // contrib module to provide fine-grained settings to ignore specific
  // projects or releases).
  \Drupal::moduleHandler()->alter('update_status', $projects);

  // Store the site's update status for at most 1 hour.
  \Drupal::keyValueExpirable('update')->setWithExpire('update_project_data', $projects, 3600);
  return $projects;
}

/**
 * Calculates the current update status of a specific project.
 *
 * For a detailed description see
 * \Drupal\update\UpdateProjectStatus::getUpdateProjectData().
 * @deprecated in Drupal 8.9.0 and will be removed before Drupal 9.0.0. There is
 *   no replacement.
 *
 * @param $project_data
 *   An array containing information about a specific project.
 * @param $available
 *   Data about available project releases of a specific project.
 */
function update_calculate_project_update_status(&$project_data, $available) {
  @trigger_error(__FUNCTION__ . '() is deprecated in Drupal 8.9.0 and will be removed before Drupal 9.0.0. There is no replacement. See https://why-did-u-call-this.com', E_USER_DEPRECATED);
  $project_data = (new UpdateProjectStatus($project_data, $available))->getUpdateProjectData();
}

import { execSync } from 'child_process';
import { commandAsWebserver } from '../globals';

/**
 * Installs Drupal Modules
 *
 * @param {array} [modules=[]]
 *   An array of modules to install
 * @param {function} callback
 *   A callback which will be called, when the installation is finished.
 * @return {object}
 *   The 'browser' object.
 */
exports.command = function moduleInstall(modules = [], callback) {
  const self = this;

  try {
    const installedModules = execSync(
      commandAsWebserver(
        `php ./scripts/test-site.php modules-install --modules ${modules.join(' ')}`,
      ),
    );
    this.installedModules = installedModules;
  }
  catch (error) {
    this.assert.fail(error);
  }

  // Nightwatch doesn't like it when no actions are added in a command file.
  // https://github.com/nightwatchjs/nightwatch/issues/1792
  this.pause(1);

  if (typeof callback === 'function') {
    callback.call(self);
  }
  return this;
};

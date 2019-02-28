/**
 * @file
 * Attaches the behaviors for the Layout Builder module's Quickedit integration.
 */

((drupalSettings, storage) => {
  /**
   * Prefix the sectionHashKey.
   *
   * @param {string} sectionHashKey
   *   The section hash key to prefix.
   *
   * @return {string}
   *   A prefixed section hash key.
   */
  function _prefixSectionHashKey(sectionHashKey) {
    return `Drupal.layout_builder.section_hashes.${sectionHashKey}`;
  }
  const sectionHashes = drupalSettings.layout_builder.section_hashes;
  Object.keys(sectionHashes).forEach(sectionHashKey => {
    const sectionHash = sectionHashes[sectionHashKey];
    const hashStorageKey = _prefixSectionHashKey(sectionHashKey);
    const storedSectionHash = storage.getItem(hashStorageKey);
    if (!storedSectionHash || storedSectionHash !== sectionHash.hash) {
      // The stored hash does not match. Clear QuickEdit metadata.
      Object.keys(storage).forEach(storageKey => {
        if (
          storageKey.indexOf(
            `Drupal.quickedit.metadata.${sectionHash.quickedit_storage_prefix}`,
          ) === 0
        ) {
          storage.removeItem(storageKey);
        }
      });
      // Store the update sections hash.
      storage.setItem(hashStorageKey, sectionHash.hash);
    }
  });
})(drupalSettings, window.sessionStorage);

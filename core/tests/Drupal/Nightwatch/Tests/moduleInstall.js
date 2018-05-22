module.exports = {
  '@tags': ['modules'],
  before(browser) {
    browser
      .installDrupal({})
      .moduleInstall(['test_page_test']);
  },
  after(browser) {
    browser
      .uninstallDrupal();
  },
  'Ensure Test page module enabled': (browser) => {
    browser
        .relativeURL('/test-page')
        .waitForElementVisible('body', 1000)
        .assert.containsText('body', 'Test page text')
        .logAndEnd({ onlyOnError: false });
  },
};

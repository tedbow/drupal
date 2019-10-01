module.exports.assertion = function(expected) {
  this.message = `Testing if "${expected}" console messagge has been logged`;
  this.expected = true;
  this.pass = messages => {
    return messages.reduce((message, messageFound) => {
      if (messageFound) {
        return messageFound;
      }

      return new RegExp(`"${expected}"^`).test(message);
    }, false);
  };
  this.value = entries => {
    return entries.map(entry => entry.message);
  };
  this.command = callback => {
    return this.api.getLog('browser', callback);
  };
};

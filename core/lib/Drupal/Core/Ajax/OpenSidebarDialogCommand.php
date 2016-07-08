<?php

namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command to open certain content in a dialog in a modal dialog.
 *
 * @ingroup ajax
 */
class OpenSidebarDialogCommand extends OpenDialogCommand {

  /**
   * Constructs an OpenSidebarDialogCommand object.
   *
   * Drupal provides a built-in sidebar for
   * this purpose, so no selector needs to be provided.
   *
   * @todo Do we need a selector? Or act the same as modal?
   *
   * @param string $title
   *   The title of the dialog.
   * @param string|array $content
   *   The content that will be placed in the dialog, either a render array
   *   or an HTML string.
   * @param array $dialog_options
   *   (optional) Settings to be passed to the dialog implementation. Any
   *   jQuery UI option can be used. See http://api.jqueryui.com/dialog.
   * @param array|null $settings
   *   (optional) Custom settings that will be passed to the Drupal behaviors
   *   on the content of the dialog. If left empty, the settings will be
   *   populated automatically from the current request.
   */
  public function __construct($title, $content, array $dialog_options = array(), $settings = NULL) {
    $dialog_options['modal'] = FALSE;
    parent::__construct('#drupal-sidebar', $title, $content, $dialog_options, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $this->dialogOptions['modal'] = FALSE;
    return array(
      'command' => 'openSidebar',
      'selector' => $this->selector,
      'settings' => $this->settings,
      'data' => $this->getRenderedContent(),
      'dialogOptions' => $this->dialogOptions,
    );
  }

}

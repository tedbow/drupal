<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\InlineBlockInterface;

/**
 * Class InlineBlockController.
 *
 *  Returns responses for Inline block routes.
 */
class InlineBlockController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Inline block  revision.
   *
   * @param int $inline_block_revision
   *   The Inline block  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($inline_block_revision) {
    $inline_block = $this->entityManager()->getStorage('inline_block')->loadRevision($inline_block_revision);
    $view_builder = $this->entityManager()->getViewBuilder('inline_block');

    return $view_builder->view($inline_block);
  }

  /**
   * Page title callback for a Inline block  revision.
   *
   * @param int $inline_block_revision
   *   The Inline block  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($inline_block_revision) {
    $inline_block = $this->entityManager()->getStorage('inline_block')->loadRevision($inline_block_revision);
    return $this->t('Revision of %title from %date', ['%title' => $inline_block->label(), '%date' => format_date($inline_block->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Inline block .
   *
   * @param \Drupal\layout_builder\Entity\InlineBlockInterface $inline_block
   *   A Inline block  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(InlineBlockInterface $inline_block) {
    $account = $this->currentUser();
    $langcode = $inline_block->language()->getId();
    $langname = $inline_block->language()->getName();
    $languages = $inline_block->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $inline_block_storage = $this->entityManager()->getStorage('inline_block');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $inline_block->label()]) : $this->t('Revisions for %title', ['%title' => $inline_block->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all inline block revisions") || $account->hasPermission('administer inline block entities')));
    $delete_permission = (($account->hasPermission("delete all inline block revisions") || $account->hasPermission('administer inline block entities')));

    $rows = [];

    $vids = $inline_block_storage->revisionIds($inline_block);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\layout_builder\InlineBlockInterface $revision */
      $revision = $inline_block_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $inline_block->getRevisionId()) {
          $link = $this->l($date, new Url('entity.inline_block.revision', ['inline_block' => $inline_block->id(), 'inline_block_revision' => $vid]));
        }
        else {
          $link = $inline_block->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => Url::fromRoute('entity.inline_block.revision_revert', ['inline_block' => $inline_block->id(), 'inline_block_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.inline_block.revision_delete', ['inline_block' => $inline_block->id(), 'inline_block_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['inline_block_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}

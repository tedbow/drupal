<?php

namespace Drupal\update\Psa;

/**
 * A security announcement.
 *
 * These come form the PSA feed on drupal.org.
 *
 * @link https://www.drupal.org/docs/8/update/automatic-updates#s-public-service-announcement-psa-feed
 */
class SecurityAnnouncement {

  /**
   * The title of the announcement.
   *
   * @var string
   */
  protected $title;

  /**
   * The project name for the announcement.
   *
   * @var string
   */
  protected $project;

  /**
   * The project type for the announcement.
   * @var string
   */
  protected $type;

  /**
   * Whether this announce is PSA instead of another type of announcement.
   * @var bool
   */
  protected $isPsa;

  /**
   * The currently insecure versions of the project.
   *
   * @var array
   */
  protected $insecureVersions;

  /**
   * The link to the announcement.
   *
   * @var string
   */
  protected $link;


  /**
   * Constructs a SecurityAnnouncement object.
   *
   * @param string $title
   * @param string $project
   * @param string $type
   * @param bool $is_psa
   * @param string $link
   * @param array $insecure_versions
   */
  public function __construct(string $title, string $project, string $type, bool $is_psa, string $link, array $insecure_versions) {
    $this->title = $title;
    $this->project = $project;
    $this->type = $type;
    $this->isPsa = $is_psa;
    $this->link = $link;
    $this->insecureVersions = $insecure_versions;
  }

  /**
   * Creates a SecurityAnnouncement instance from an array.
   *
   * @param $data
   *   The security announcement data as returned from the JSON feed.
   *
   * @return static
   *   A new SecurityAnnouncement object.
   */
  public static function createFromArray($data) {
    $expected_keys = ['title', 'project', 'type', 'is_psa', 'link', 'insecure'];
    if (array_diff_key(array_flip($expected_keys), $data)) {
      throw new \UnexpectedValueException("The PSA item is malformed.");
    }
    return new static(
      $data['title'],
      $data['project'],
      $data['type'],
      $data['is_psa'],
      $data['link'],
      $data['insecure']
    );
  }

  /**
   * Gets the title.
   *
   * @return string
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * Gets the project associated with the announcement.
   *
   * @return string
   */
  public function getProject(): string {
    return $this->project;
  }

  /**
   * Gets the type of project associated with the announcement.
   *
   * @return string
   */
  public function getProjectType(): string {
    return $this->type;
  }

  /**
   * Whether the security announcement is PSA or not.
   *
   * @return bool
   */
  public function isPsa(): bool {
    return $this->isPsa;
  }

  /**
   * Gets the currently insecure version of the project.
   *
   * @return string[]
   *   The versions of the project that are currently insecure.
   */
  public function getInsecureVersions(): array {
    return $this->insecureVersions;
  }

  /**
   * Gets the links to the security announcement.
   *
   * @return string
   *    The link.
   */
  public function getLink(): string {
    return $this->link;
  }

}

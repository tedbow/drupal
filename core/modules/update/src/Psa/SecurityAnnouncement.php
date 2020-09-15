<?php


namespace Drupal\update\Psa;


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
   * SecurityAnnouncement constructor.
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

  public static function createFromArray($data) {
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
   * @return string
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * @return string
   */
  public function getProject(): string {
    return $this->project;
  }

  /**
   * @return string
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * @return bool
   */
  public function isPsa(): bool {
    return $this->isPsa;
  }

  /**
   * @return array
   */
  public function getInsecureVersions(): array {
    return $this->insecureVersions;
  }

  /**
   * @return string
   */
  public function getLink(): string {
    return $this->link;
  }

}

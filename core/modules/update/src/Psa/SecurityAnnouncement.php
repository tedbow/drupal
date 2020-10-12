<?php

namespace Drupal\update\Psa;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * Provides a security announcement value object.
 *
 * These come from the PSA feed on drupal.org.
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
   *
   * @var string
   */
  protected $type;

  /**
   * Whether this announcement is a PSA instead of another type of announcement.
   *
   * @var bool
   */
  protected $isPsa;

  /**
   * The currently insecure versions of the project.
   *
   * @var string[]
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
   *   The title of the announcement.
   * @param string $project
   *   The project name.
   * @param string $type
   *   The project type.
   * @param bool $is_psa
   *   Whether this announcement is a PSA.
   * @param string $link
   *   The link to the announcement.
   * @param string[] $insecure_versions
   *   The versions of the project that are currently insecure. For PSAs this
   *   list does include versions that will be marked as insecure when the new
   *   security release is published.
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
   * @param mixed[] $data
   *   The security announcement data as returned from the JSON feed.
   *
   * @return static
   *   A new SecurityAnnouncement object.
   *
   * @throws \UnexpectedValueException
   *   Thrown if the array is not a valid PSA.
   */
  public static function createFromArray(array $data): SecurityAnnouncement {
    static::validateAnnouncementData($data);
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
   * Validates the PSA data.
   *
   * @param mixed[] $data
   *   The announcement data.
   *
   * @throws \UnexpectedValueException
   *   Thrown if PSA data is not valid.
   */
  protected static function validateAnnouncementData(array $data): void {
    $not_blank_constraints = [
      new Type(['type' => 'string']),
      new NotBlank(),
    ];
    $collection_constraint = new Collection([
      'fields' => [
        'title' => $not_blank_constraints,
        'project' => $not_blank_constraints,
        'type' => $not_blank_constraints,
        'link' => $not_blank_constraints,
        'is_psa' => new NotBlank(),
        'insecure' => new Type(['type' => 'array']),
      ],
      'allowExtraFields' => TRUE,
    ]);
    $violations = Validation::createValidator()->validate($data, $collection_constraint);
    if ($violations->count()) {
      foreach ($violations as $violation) {
        $violation_messages[] = (string) $violation;
      }
      throw new \UnexpectedValueException(implode(",  \n", $violation_messages));
    }
  }

  /**
   * Gets the title.
   *
   * @return string
   *   The project title.
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * Gets the project associated with the announcement.
   *
   * @return string
   *   The project name.
   */
  public function getProject(): string {
    return $this->project;
  }

  /**
   * Gets the type of project associated with the announcement.
   *
   * @return string
   *   The project type.
   */
  public function getProjectType(): string {
    return $this->type;
  }

  /**
   * Whether the security announcement is a PSA or not.
   *
   * @return bool
   *   TRUE if the announcement is a PSA otherwise, FALSE.
   */
  public function isPsa(): bool {
    return $this->isPsa;
  }

  /**
   * Gets the currently insecure versions of the project.
   *
   * @return string[]
   *   The versions of the project that are currently insecure.
   */
  public function getInsecureVersions(): array {
    return $this->insecureVersions;
  }

  /**
   * Gets the link to the security announcement.
   *
   * @return string
   *   The link to the PSA.
   */
  public function getLink(): string {
    return $this->link;
  }

}

<?php

namespace Drupal\update\Psa;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

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
   * @param array $data
   *   The security announcement data as returned from the JSON feed.
   *
   * @return static
   *   A new SecurityAnnouncement object.
   *
   * @throws \UnexpectedValueException
   *   Thrown if the array is not a valid PSA.
   */
  public static function createFromArray(array $data) {
    static::validatePsaData($data);
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
   * @param array $data
   *
   * @throws \UnexpectedValueException
   *   Thrown if PSA data is not valid.
   */
  protected static function validatePsaData(array $data): void {
    $new_blank_constraints = [
      new Type(['type' => 'string']),
      new NotBlank(),
    ];
    $collection_constraint = new Collection([
      'fields' => [
        'title' => $new_blank_constraints,
        'project' => $new_blank_constraints,
        'type' => $new_blank_constraints,
        'link' => $new_blank_constraints,
        'is_psa' => new NotBlank(),
        'insecure' => new Type(['type' => 'array']),
      ],
      'allowExtraFields' => TRUE,
    ]);
    $violations = Validation::createValidator()->validate($data, $collection_constraint);
    if ($violations->count()) {
      foreach ($violations as $violation) {
        $volition_messages[] = (string) $violation;
      }
      throw new \UnexpectedValueException(implode(",  \n", $volition_messages));
    }
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
   *   The link.
   */
  public function getLink(): string {
    return $this->link;
  }

}

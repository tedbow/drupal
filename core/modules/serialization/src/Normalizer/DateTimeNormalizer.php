<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\TypedData\Type\DateTimeInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts values for datetime objects to RFC3339 and from common formats.
 *
 * @internal
 *
 * Note that this is class can become the 'serializer.normalizer.datetime'
 * service in Drupal 9.0.0 and allow the 'serializer.normalizer.datetimeiso8601'
 * service to be removed in Drupal 9.0.0. That is not possible today, because
 * this class also works for \Drupal\Core\TypedData\Plugin\DataType\Timestamp
 * objects, but those must be ignored while the 'bc_timestamp_normalizer_unix'
 * BC flag is enabled. If this class were already an active service, it'd cause
 * 'timestamp' fields to not have (numeric) UNIX timestamps as normalized values
 * anymore, which would break BC.
 */
class DateTimeNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * Allowed datetime formats for the denormalizer.
   *
   * The list is chosen to be unambiguous and language neutral, but also common
   * for data interchange.
   *
   * @var string[]
   *
   * @see http://php.net/manual/en/datetime.createfromformat.php
   */
  protected $allowedFormats = [
    'RFC 3339' => \DateTime::RFC3339,
    'ISO 8601' => \DateTime::ISO8601,
  ];

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = DateTimeInterface::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($datetime, $format = NULL, array $context = []) {
    return $datetime->getDateTime()
      // Set an explicit timezone. Otherwise, timestamps may end up being
      // normalized using the user's preferred timezone. Which would result in
      // many variations and complex caching.
      // @see \Drupal\Core\Datetime\DrupalDateTime::prepareTimezone()
      // @see drupal_get_user_timezone()
      ->setTimezone($this->getNormalizationTimezone())
      ->format(\DateTime::RFC3339);
  }

  /**
   * Gets the timezone to be used during normalization.
   *
   * @see ::normalize
   *
   * @returns \DateTimeZone
   *   The timezone to use.
   */
  protected function getNormalizationTimezone() {
    $default_site_timezone = \Drupal::config('system.date')->get('timezone.default');
    return new \DateTimeZone($default_site_timezone);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    // First check for a provided format, and if provided, create \DateTime
    // object using it.
    if (!empty($context['datetime_format'])) {
      return \DateTime::createFromFormat($context['datetime_format'], $data);
    }

    // Loop through the allowed formats and create a \DateTime from the
    // input data if it matches the defined pattern. Since the formats are
    // unambiguous (i.e., they reference an absolute time with a defined time
    // zone), only one will ever match.
    foreach ($this->allowedFormats as $format) {
      $date = \DateTime::createFromFormat($format, $data);
      if ($date !== FALSE) {
        return $date;
      }
    }

    $format_strings = [];

    foreach ($this->allowedFormats as $label => $format) {
      $format_strings[] = "\"$format\" ($label)";
    }

    $formats = implode(', ', $format_strings);
    throw new UnexpectedValueException(sprintf('The specified date "%s" is not in an accepted format: %s.', $data, $formats));
  }

}

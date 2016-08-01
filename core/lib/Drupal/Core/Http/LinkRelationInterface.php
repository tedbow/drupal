<?php

namespace Drupal\Core\Http;

/**
 * Defines a single link relations.
 *
 * An example of a link relationship is 'canonical'. It represents a canonical,
 * definite representation of a resource.
 *
 * @see https://tools.ietf.org/html/rfc5988#page-6
 */
interface LinkRelationInterface {

  /**
   * Returns the link relation name.
   *
   * @return string
   *   The name of the relation.
   *
   * @see https://tools.ietf.org/html/rfc5988#section-6.2.1
   */
  public function getName();

  /**
   * If the relationship is not a IANA one, provide the URL to the definition.
   *
   * @return string
   *   The relationship URL.
   */
  public function getRelationshipUrl();

  /**
   * Returns the link relation description.
   *
   * @return string
   *   The link relation description.
   *
   * @see https://tools.ietf.org/html/rfc5988#section-6.2.1
   */
  public function getDescription();

  /**
   * Returns the URL pointing to the reference of the link relation.
   *
   * @return string
   *   The URL pointing to the reference.
   *
   * @see https://tools.ietf.org/html/rfc5988#section-6.2.1
   */
  public function getReference();

  /**
   * Returns some extra notes/comments about this link relation.
   *
   * @return string
   *
   * @see https://tools.ietf.org/html/rfc5988#section-6.2.1
   */
  public function getNotes();

}

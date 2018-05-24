<?php


namespace Drupal\Core\Access;


use Drupal\Core\Session\AccountInterface;

trait AccessDependentTrait {

  /**
   * The object that are depended on.
   *
   * @var \Drupal\Core\Access\AccessibleInterface
   */
  protected $accessDependee;

  /**
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependee
   */
  public function setAccessDependee(AccessibleInterface $access_dependee) {

  }

  /**
   * Gets the access dependees.
   *
   * @return \Drupal\Core\Access\AccessibleInterface
   *   The access dependees.
   */
  public function getAccessDependee() {

  }

  /*function dependeeAccess($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$this->accessDependees) {
      return AccessResult::forbidden();
    }
    $access = NULL;
    /** @var  \Drupal\Core\Access\AccessibleInterface $accessDependee */
    foreach ($this->accessDependees as $accessDependee) {
      if ($access === NULL) {
        $access = $accessDependee->access($operation, $account, TRUE);
      }
      $access->andIf($accessDependee->access($operation, $account, TRUE));
    }
    return $access;
  }*/
}

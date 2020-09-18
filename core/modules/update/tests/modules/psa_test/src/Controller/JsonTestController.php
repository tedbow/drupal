<?php

namespace Drupal\psa_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class JsonTestController.
 */
class JsonTestController extends ControllerBase {

  const STATE_EXTRA_ITEM_KEY = 'STATE_EXTRA_ITEM_KEY';

  /**
   * Test JSON controller.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return JSON feed response.
   */
  public function json() {
    $feed = [];
    $feed[] = [
      'title' => 'Critical Release - SA-2019-02-19',
      'link' => 'https://www.drupal.org/sa-2019-02-19',
      'project' => 'drupal',
      'type' => 'core',
      'insecure' => [
        '7.65',
        '8.5.14',
        '8.5.14',
        '8.6.13',
        '8.7.0-alpha2',
        '8.7.0-beta1',
        '8.7.0-beta2',
        '8.6.14',
        '8.6.15',
        '8.6.15',
        '8.5.15',
        '8.5.15',
        '7.66',
        '8.7.0',
        \Drupal::VERSION,
      ],
      'is_psa' => '0',
      'pubDate' => 'Tue, 19 Feb 2019 14:11:01 +0000',
    ];
    // Add a core PSA that does not match the installed version of core.
    // 'is_psa' is not empty therefore the PSA will be displayed.
    $feed[] = [
      'title' => 'Critical Release - PSA-Really Old',
      'link' => 'https://www.drupal.org/psa',
      'project' => 'drupal',
      'type' => 'core',
      'is_psa' => '1',
      'insecure' => [],
      'pubDate' => 'Tue, 19 Feb 2017 14:11:01 +0000',
    ];
    // Add an SA that matches the 'aaa_update_project' project name. This is the
    // project name set to be used in the tests. This will show because it
    // matches the version number.
    // @see \Drupal\Tests\update\Functional\PsaTest::setUp
    $feed[] = [
      'title' => 'AAA Update Project - Moderately critical - Access bypass - SA-CONTRIB-2019',
      'link' => 'https://www.drupal.org/sa-contrib-2019',
      'project' => 'aaa_update_project',
      'type' => 'theme',
      'is_psa' => '0',
      'insecure' => ['8.x-1.1', '8.x-8.7.0'],
      'pubDate' => 'Tue, 19 Mar 2019 12:50:00 +0000',
    ];
    // Add an SA that is the same as above except that it uses the
    // 'aaa_update_test' project name. This will not match because it matches
    // the module name but not the module's project name.
    $feed[] = [
      'title' => 'AAA Update Test - Moderately critical - Access bypass - SA-CONTRIB-2019',
      'link' => 'https://www.drupal.org/sa-contrib-2019',
      'project' => 'aaa_update_test',
      'type' => 'theme',
      'is_psa' => '0',
      'insecure' => ['8.x-1.1', '8.x-8.7.0'],
      'pubDate' => 'Tue, 19 Mar 2019 12:50:00 +0000',
    ];
    // Add an item for project that is present but not enabled. The PSA will not
    // be displayed.
    $feed[] = [
      'title' => 'BBB Update project - Moderately critical - Access bypass - SA-CONTRIB-2019',
      'link' => 'https://www.drupal.org/sa-contrib-2019',
      'project' => 'bbb_update_project',
      'type' => 'module',
      'is_psa' => '1',
      'insecure' => [],
      'pubDate' => 'Tue, 19 Mar 2019 12:50:00 +0000',
    ];
    // Add an item for project that is missing. The PSA will not
    // be displayed.
    $feed[] = [
      'title' => 'Missing Project - Moderately critical - Access bypass - SA-CONTRIB-2019',
      'link' => 'https://www.drupal.org/sa-contrib-2019',
      'project' => 'missing_project',
      'type' => 'module',
      'is_psa' => '1',
      'insecure' => ['7.x-1.7', '8.x-1.4'],
      'pubDate' => 'Tue, 19 Mar 2019 12:50:00 +0000',
    ];
    if ($this->state()->get(static::STATE_EXTRA_ITEM_KEY)) {
      $feed[] = [
        'title' => 'A new Critical Release',
        'link' => 'https://www.drupal.org/psa',
        'project' => 'drupal',
        'type' => 'core',
        'is_psa' => '1',
        'insecure' => [],
        'pubDate' => 'Tue, 19 Feb 2017 14:11:01 +0000',
      ];
    }
    return new JsonResponse($feed);
  }

}

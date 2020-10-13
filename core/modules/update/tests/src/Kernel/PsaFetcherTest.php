<?php

namespace Drupal\Tests\update\Kernel;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @coversDefaultClass \Drupal\update\Psa\PsaFetcher
 *
 * @group update
 */
class PsaFetcherTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'update',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('update');
  }

  /**
   * Tests contrib advisories that should be displayed.
   *
   * @param mixed[] $feed_item
   *   The feed item to test. 'title' and 'link' are omitted from this array
   *   because they do not need to vary between test cases.
   * @param string|null $existing_version
   *   The existing version of the module.
   *
   * @dataProvider providerShowAdvisories
   */
  public function testShowAdvisories(array $feed_item, string $existing_version = NULL): void {
    $this->setProphesizedServices($feed_item, $existing_version);
    $fetcher = $this->container->get('update.psa_fetcher');
    /** @var \Drupal\Component\Render\FormattableMarkup[] $links */
    $links = $fetcher->getPublicServiceMessages();
    static::assertCount(1, $links);
    $this->assertSame('<a href="http://thesa.com">SA title</a>', (string) $links[0]);
  }

  /**
   * Dataprovider for testShowAdvisories().
   */
  public function providerShowAdvisories() {
    return [
      'contrib:exact:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:semver:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['1.0.0'],
        ],
        'existing_version' => '1.0.0',
      ],
      'contrib:exact:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:not-exact:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0'],
        ],
        'existing_version' => '1.0',

      ],
      'contrib:non-matching:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0'],
        ],
        'existing_version' => '8.x-2.0',
      ],
      'contrib:no-insecure:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => [],
        ],
        'existing_version' => '8.x-2.0',
      ],
      'contrib:dev:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => [],
        ],
        'existing_version' => '8.x-2.x-dev',
      ],
      'core:exact:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => [\Drupal::VERSION],
        ],
      ],
      'core:exact:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => [\Drupal::VERSION],
        ],
      ],
      'core:not-exact:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => ['9.1'],
        ],

      ],
      'core:non-matching:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => ['9.0.0'],
        ],
      ],
      'core:no-insecure:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => [],
        ],
      ],
    ];
  }

  /**
   * Tests Advisories that should be ignored.
   *
   * @param mixed[] $feed_item
   *   The feed item to test. 'title' and 'link' are omitted from this array
   *   because they do not need to vary between test cases.
   * @param string|null $existing_version
   *   The existing version of the module.
   *
   * @dataProvider providerIgnoreAdvisories
   */
  public function testIgnoreAdvisories(array $feed_item, string $existing_version = NULL): void {
    $this->setProphesizedServices($feed_item, $existing_version);
    $fetcher = $this->container->get('update.psa_fetcher');
    /** @var \Drupal\Component\Render\FormattableMarkup[] $links */
    $links = $fetcher->getPublicServiceMessages();
    static::assertCount(0, $links);
  }

  /**
   * Dataprovider for testIgnoreAdvisories().
   */
  public function providerIgnoreAdvisories() {
    return [
      'contrib:not-exact:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['1.0'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:non-matching:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.1'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:not-exact:non-psa-reversed' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['1.0'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:semver-non-exact:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['1.0'],
        ],
        'existing_version' => '1.0.0',
      ],
      'contrib:semver-major-match:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['1.0'],
        ],
        'existing_version' => '1.0.0',
      ],
      'contrib:non-matching-not-exact:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['1.1'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:both-extra:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0-extraStringNotSpecial'],
        ],
        'existing_version' => '8.x-1.0-alsoNotSpecialNotMatching',
      ],
      'contrib:semver-7major-match:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['7.x-1.0'],
        ],
        'existing_version' => '1.0.0',
      ],
      'contrib:semver-different-majors:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['7.x-1.0'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:no-version:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.1'],
        ],
        'existing_version' => '',
      ],
      'contrib:insecure-extra:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0-extraStringNotSpecial'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:insecure-dev:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0-dev'],
        ],
        'existing_version' => '8.x-1.0',
      ],
      'contrib:exiting-dev:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0'],
        ],
        'existing_version' => '8.x-1.x-dev',
      ],
      'contrib:non-existing-project:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'non_existing_project',
          'insecure' => ['8.x-1.0'],
        ],
      ],
      'contrib:non-existing-project:psa' => [
        'feed_item' => [
          'is_psa' => 1,
          'type' => 'module',
          'project' => 'non_existing_project',
          'insecure' => ['8.x-1.0'],
        ],
      ],
      'core:non-matching:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => ['9.0.0'],
        ],
      ],
      'core:non-matching-not-exact:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => ['9.1'],
        ],
      ],
      'core:no-insecure:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'core',
          'project' => 'drupal',
          'insecure' => [],
        ],
      ],
      'contrib:existing-extra:non-psa' => [
        'feed_item' => [
          'is_psa' => 0,
          'type' => 'module',
          'project' => 'the_project',
          'insecure' => ['8.x-1.0'],
        ],
        'existing_version' => '8.x-1.0-extraStringNotSpecial',

      ],
    ];
  }

  /**
   * Sets prophesized 'http_client' and 'extension.list.module' services.
   *
   * @param mixed[] $feed_item
   *   The feed item to test. 'title' and 'link' are omitted from this array
   *   because they do not need to vary between test cases.
   * @param string|null $existing_version
   *   The existing version of the module.
   */
  protected function setProphesizedServices(array $feed_item, string $existing_version = NULL): void {
    $feed_item += [
      'title' => 'SA title',
      'link' => 'http://thesa.com',
    ];
    $json_string = json_encode([$feed_item]);
    $stream = $this->prophesize(StreamInterface::class);
    $stream->__toString()->willReturn($json_string);
    $response = $this->prophesize(ResponseInterface::class);
    $response->getBody()->willReturn($stream->reveal());
    $client = $this->prophesize(Client::class);
    $client->get('https://updates.drupal.org/psa.json')
      ->willReturn($response->reveal());
    $this->container->set('http_client', $client->reveal());

    if ($existing_version) {
      $module_list = $this->prophesize(ModuleExtensionList::class);
      $extension = $this->prophesize(Extension::class)->reveal();
      $extension->info = [
        'version' => $existing_version,
        'project' => 'the_project',
      ];
      $module_list->getList()->willReturn([$extension]);

      $this->container->set('extension.list.module', $module_list->reveal());
    }
  }
}

<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\UnitTestCase;
use Drupal\update\Psa\PsaFetcher;
use GuzzleHttp\Client;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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
   * @param string $existingVersion
   * @param array $insecure_versions
   *
   * @dataProvider providerGetPublicServiceMessages
   */
  public function testGetPublicServiceMessages(string $existingVersion, int $is_psa, array $insecure_versions, bool $expectedMatch) {

    $sa = [
      'title' => 'SA title',
      'project' => 'the_project',
      'type' => 'module',
      'link' => 'http://thesa.com',
      'insecure' => $insecure_versions,
      'is_psa' => $is_psa,
    ];
    $json_string = json_encode([$sa]);
    $stream = $this->prophesize(StreamInterface::class);
    $stream->__toString()->willReturn($json_string);
    $response = $this->prophesize(ResponseInterface::class);
    $response->getBody()->willReturn($stream->reveal());
    $client = $this->prophesize(Client::class);
    $client->get('https://updates.drupal.org/psa.json')->willReturn($response->reveal());
    $this->container->set('http_client', $client->reveal());

    $module_list = $this->prophesize(ModuleExtensionList::class);
    $extension = $this->prophesize(Extension::class)->reveal();
    $extension->info = [
      'version' => $existingVersion,
      'project' => 'the_project',
    ];
    $module_list->getList()->willReturn([$extension]);

    $this->container->set('extension.list.module', $module_list->reveal());

    $fetcher = $this->container->get('update.psa_fetcher');
    /** @var \Drupal\Component\Render\FormattableMarkup[] $links */
    $links = $fetcher->getPublicServiceMessages();
    if ($expectedMatch) {
      static::assertCount(1, $links);
      $this->assertSame('<a href="http://thesa.com">SA title</a>', (string) $links[0]);
    }
    else {
      static::assertCount(0, $links);
    }

  }

  public function providerGetPublicServiceMessages() {
    return [
      [
        'existing_version' => '8.x-1.0',
        'is_psa' => 0,
        'insecure_versions' => ['8.x-1.0'],
        TRUE,
      ],
      [
        'existing_version' => '8.x-1.0',
        'is_psa' => 0,
        'insecure_versions' => ['8.x-1.1'],
        FALSE,
      ],
    ];
  }

}

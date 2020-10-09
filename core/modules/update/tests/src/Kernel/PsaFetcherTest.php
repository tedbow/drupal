<?php

namespace Drupal\Tests\update\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ExtensionList;
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


  public function testGetPublicServiceMessages() {

    $json_string = json_encode(['is_psa' => TRUE]);
    $stream = $this->prophesize(StreamInterface::class);
    $stream->__toString()->willReturn($json_string);
    $message = $this->prophesize(MessageInterface::class);
    $message->getBody()->willReturn($json_string);
    $response = $this->prophesize(ResponseInterface::class);
    $response->getBody()->willReturn($stream->reveal());
    $client = $this->prophesize(Client::class);
    $client->get('https://updates.drupal.org/psa.json')->willReturn($response->reveal());
    $this->container->set('http_client', $client->reveal());

    $fetcher = $this->container->get('update.psa_fetcher');
    $fetcher->getPublicServiceMessages();

  }

}

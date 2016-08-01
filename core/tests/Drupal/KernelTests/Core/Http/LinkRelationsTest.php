<?php

namespace Drupal\KErnelTests\Core\Http;

use Drupal\Core\Http\LinkRelation;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests link relationships in Drupal.
 *
 * @group HTTP
 */
class LinkRelationsTest extends KernelTestBase {

  public function testAvailableLinkRelationships() {
    /** @var \Drupal\Core\Http\LinkRelationManager $link_relation_manager */
    $link_relation_manager = \Drupal::service('plugin.manager.link_relation');
    /** @var  $canonical */
    /** @var \Drupal\Core\Http\LinkRelationInterface $canonical */
    $canonical = $link_relation_manager->createInstance('canonical');
    $this->assertInstanceOf(LinkRelation::class, $canonical);
    $this->assertEquals('[RFC6596]', $canonical->getReference());

    // Test a couple of examples.
    $this->assertContains('about', array_keys($link_relation_manager->getDefinitions()));
    $this->assertContains('original', array_keys($link_relation_manager->getDefinitions()));
    $this->assertContains('type', array_keys($link_relation_manager->getDefinitions()));
  }

}

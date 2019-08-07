<?php

namespace Drupal\Tests\system\FunctionalJavascript;

use Behat\Mink\Driver\Selenium2Driver;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Class DebugTest
 *
 * @group debug
 */
class DebugTest extends WebDriverTestBase {

  public function setUp() {
    parent::setUp(); // TODO: Change the autogenerated stub
  }

  /**
   * {@inheritdoc}
   *
   * To use a legacy phantomjs based approach, please use PhantomJSDriver::class.
   */
  //protected $minkDefaultDriverClass = Selenium2Driver::class;

  public function testXPath() {
    $driver = $this->getSession()->getDriver();
    $this->assertEquals('Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver', get_class($driver));
    $this->drupalGet('debug.html');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->assertEquals('//html', $page->getXpath());
    $this->assertNotEmpty($driver->getContent());
    //file_put_contents('/Users/ted.bowman/Sites/www/ted.html', $driver->getContent());
    /** @var \Behat\Mink\Element\NodeElement $body_element */
    $body_element = $driver->find('//*[@id="findme"]')[0];
    self::assertNotEmpty($body_element);
    $this->assertEquals('h1', $body_element->getTagName());
    $assert_session->elementExists('xpath', '/body');
    $assert_session->elementNotExists('xpath', '/aksdjfajsdf');
    $assert_session->elementExists('css', 'body');
  }

  public function testDirectXPath() {
    //$this->drupalGet('validxml.xml');
//    $this->getSession()->visit(' http://d8/validxml.xml');
//    $driver = $this->getSession()->getDriver();
//    $this->assertEquals('Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver', get_class($driver));
//    $this->assertNotEmpty($driver->getContent());
    $doc = new \DOMDocument();
    $file = '/Users/ted.bowman/Sites/www/d8/validxml.xml';
    $doc->load($file);

    $xpath = new \DOMXPath($doc);

    // example 1: for everything with an id
    $elements = $xpath->query("//book");
    $this->assertNotEmpty($elements);
  }


  public function testVisitXPath() {
    //$this->drupalGet('validxml.xml');
    $this->getSession()->visit(' http://d8/validxml.xml');
    $driver = $this->getSession()->getDriver();
    //    $this->assertEquals('Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver', get_class($driver));
    //    $this->assertNotEmpty($driver->getContent());
    $doc = new \DOMDocument();
    $content = $driver->getContent();
    $this->assertSame(file_get_contents('/Users/ted.bowman/Sites/www/d8/validxml-from-visit.xml'), $content);
    $doc->loadXML($content);

    $xpath = new \DOMXPath($doc);

    // example 1: for everything with an id
    $elements = $xpath->query("//book");
    $this->assertNotEmpty($elements);
  }

}

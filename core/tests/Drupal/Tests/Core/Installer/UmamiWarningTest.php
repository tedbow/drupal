<?php
namespace Drupal\Tests\Core\Installer {
  use Drupal\Core\Form\FormState;
  use Drupal\Core\Installer\Form\SelectProfileForm;
  use Drupal\Core\StringTranslation\TranslationInterface;
  use Drupal\Tests\Mockbuilder;
  use Drupal\Tests\UnitTestCase;
  use Prophecy\Argument;

  /**
   * Test the 
   */
  class UmamiWarningTest extends UnitTestCase {

    public function testWarning() {
      // Mock the standard extension.
      $standard_profile = $this->prophesize('\Drupal\Core\Extension\Extension');
      $standard_profile->getName()
        ->willReturn('standard')
        ->shouldBeCalled();
      // Mock the demo_umami extension.
      $umami_profile = $this->prophesize('\Drupal\Core\Extension\Extension');
      $umami_profile->getName()
        ->willReturn('demo_umami')
        ->shouldBeCalled();
      $install_state = [
        'profiles' => [$standard_profile->reveal(), $umami_profile->reveal()],
      ];
      $form_state = (new FormState())
        ->addBuildInfo('args', [&$install_state])
        ->disableRedirect();


      $string_translation = $this->prophesize(TranslationInterface::class);
      $string_translation->translate(Argument::cetera())->shouldNotBeCalled();
      $string_translation->formatPlural(Argument::cetera())->shouldNotBeCalled();
      $string_translation->translateString(Argument::cetera())->will(function ($args) {
        return $args[0]->getUntranslatedString();
      });

      $form_object = new SelectProfileForm();
      $form_object->setStringTranslation($string_translation->reveal());
      $form = $form_object->buildForm([], $form_state, $install_state);
      $elements_keys = ['warning', 'description'];
      // Check the at warning element is before the description elment.
      $this->assertEquals($elements_keys, array_keys($form['profile']['demo_umami']['#description']));
      foreach ($elements_keys as $elements_key) {
        $element = $form['profile']['demo_umami']['#description'][$elements_key];
        $this->assertTrue(empty($element['#weight']));
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $markup */
        $markup = $form['profile']['demo_umami']['#description'][$elements_key]['#markup'];
        $this->assertEquals('Drupal\Core\StringTranslation\TranslatableMarkup', get_class($markup));
        if ($elements_key === 'demo_umami') {

          $this->assertEquals('Warning: this is a sample website, you should not use it as the basis for your website.', $markup->getUntranslatedString(), 'The demo warning text is correct.');
          $this->assertEquals(
            [
              'visible' => [
                ':input[name="profile"]' => ['value' => 'demo_umami'],
              ],
            ],
            $element['#states'],
            'The demo profile warning is set to only show when the "demo_umami" profile is selected.'
          );
        }
      }
    }

  }
}

/**
 * Provide the global functions that
 * \Drupal\Core\Installer\Form\SelectProfileForm::buildForm() relies on.
 */
namespace {
  if (!function_exists('drupal_valid_test_ua')) {

    function drupal_valid_test_ua($new_prefix = NULL) {
      return FALSE;
    }
  }
  if (!function_exists('install_profile_info')) {

    function install_profile_info($profile, $langcode = 'en') {
      $defaults = [
        'dependencies' => [],
        'themes' => ['stark'],
        'description' => '',
        'version' => NULL,
        'hidden' => FALSE,
        'php' => '5.5.9',
      ];
      if ($profile === 'demo_umami') {
        return [
          'name' => 'Umami Demo - <strong>Experimental</strong>',
          'description' => 'Install with the <i>Umami</i> food magazine demonstration website, a sample Drupal website that shows off some of the features of what is possible with Drupal "Out of the Box".',
        ] + $defaults;
      }
      else {
        return [
          'name' => 'Standard',
          'description' => 'Install with commonly used features pre-configured.',
        ] + $defaults;
      }
    }
  }
}
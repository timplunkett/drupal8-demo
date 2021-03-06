<?php

/**
 * @file
 * Definition of Drupal\language\Tests\LanguageUrlRewritingTest.
 */

namespace Drupal\language\Tests;

use Drupal\Core\Language\Language;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\simpletest\WebTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that URL rewriting works as expected.
 *
 * @group language
 */
class LanguageUrlRewritingTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language', 'language_test');

  protected function setUp() {
    parent::setUp();

    // Create and login user.
    $this->web_user = $this->drupalCreateUser(array('administer languages', 'access administration pages'));
    $this->drupalLogin($this->web_user);

    // Install French language.
    $edit = array();
    $edit['predefined_langcode'] = 'fr';
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Enable URL language detection and selection.
    $edit = array('language_interface[enabled][language-url]' => 1);
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Check that drupalSettings contains path prefix.
    $this->drupalGet('fr/admin/config/regional/language/detection');
    $this->assertRaw('"pathPrefix":"fr\/"', 'drupalSettings path prefix contains language code.');
  }

  /**
   * Check that non-installed languages are not considered.
   */
  function testUrlRewritingEdgeCases() {
    // Check URL rewriting with a non-installed language.
    $non_existing = new Language(array('id' => $this->randomMachineName()));
    $this->checkUrl($non_existing, 'Path language is ignored if language is not installed.', 'URL language negotiation does not work with non-installed languages');

    // Check that URL rewriting is not applied to subrequests.
    $this->drupalGet('language_test/subrequest');
    $this->assertText($this->web_user->getUsername(), 'Page correctly retrieved');
  }

  /**
   * Check URL rewriting for the given language.
   *
   * The test is performed with a fixed URL (the default front page) to simply
   * check that language prefixes are not added to it and that the prefixed URL
   * is actually not working.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   * @param string $message1
   *   Message to display in assertion that language prefixes are not added.
   * @param string $message2
   *   The message to display confirming prefixed URL is not working.
   */
  private function checkUrl($language, $message1, $message2) {
    $options = array('language' => $language, 'script' => '');
    $base_path = trim(base_path(), '/');
    $rewritten_path = trim(str_replace($base_path, '', \Drupal::url('<front>', array(), $options)), '/');
    $segments = explode('/', $rewritten_path, 2);
    $prefix = $segments[0];
    $path = isset($segments[1]) ? $segments[1] : $prefix;

    // If the rewritten URL has not a language prefix we pick a random prefix so
    // we can always check the prefixed URL.
    $prefixes = language_negotiation_url_prefixes();
    $stored_prefix = isset($prefixes[$language->getId()]) ? $prefixes[$language->getId()] : $this->randomMachineName();
    if ($this->assertNotEqual($stored_prefix, $prefix, $message1)) {
      $prefix = $stored_prefix;
    }

    $this->drupalGet("$prefix/$path");
    $this->assertResponse(404, $message2);
  }

  /**
   * Check URL rewriting when using a domain name and a non-standard port.
   */
  function testDomainNameNegotiationPort() {
    global $base_url;
    $language_domain = 'example.fr';
    // Get the current host URI we're running on.
    $base_url_host = parse_url($base_url, PHP_URL_HOST);
    $edit = array(
      'language_negotiation_url_part' => LanguageNegotiationUrl::CONFIG_DOMAIN,
      'domain[en]' => $base_url_host,
      'domain[fr]' => $language_domain
    );
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));
    // Rebuild the container so that the new language gets picked up by services
    // that hold the list of languages.
    $this->rebuildContainer();

    // Enable domain configuration.
    \Drupal::config('language.negotiation')
      ->set('url.source', LanguageNegotiationUrl::CONFIG_DOMAIN)
      ->save();

    // Reset static caching.
    $this->container->get('language_manager')->reset();

    // In case index.php is part of the URLs, we need to adapt the asserted
    // URLs as well.
    $index_php = strpos(\Drupal::url('<front>', array(), array('absolute' => TRUE)), 'index.php') !== FALSE;

    $request = Request::createFromGlobals();
    $server = $request->server->all();
    $request = $this->prepareRequestForGenerator(TRUE, array('HTTP_HOST' => $server['HTTP_HOST'] . ':88'));

    // Create an absolute French link.
    $language = \Drupal::languageManager()->getLanguage('fr');
    $url = _url('', array(
      'absolute' => TRUE,
      'language' => $language,
    ));

    $expected = ($index_php ? 'http://example.fr:88/index.php' : 'http://example.fr:88') . rtrim(base_path(), '/') . '/';

    $this->assertEqual($url, $expected, 'The right port is used.');

    // If we set the port explicitly in _url(), it should not be overriden.
    $url = _url('', array(
      'absolute' => TRUE,
      'language' => $language,
      'base_url' => $request->getBaseUrl() . ':90',
    ));

    $expected = $index_php ? 'http://example.fr:90/index.php' : 'http://example.fr:90' . rtrim(base_path(), '/') . '/';

    $this->assertEqual($url, $expected, 'A given port is not overriden.');

  }

}

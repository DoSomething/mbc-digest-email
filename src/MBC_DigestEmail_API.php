<?php
/**
 * MBC_DigestEmail_API - Access to mb-digest-api.
 */
namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\MB_Toolbox\MB_Toolbox_cURL;
use DoSomething\StatHat\Client as StatHat;

/**
 * MBC_DigestEmail_API class - Properties and methods used to access mb-digest-api.
 */
class MBC_DigestEmail_API
{

  /**
   * Singleton instance of application configuration settings.
   * @var object $mbConfig
   */
  private $mbConfig;

  /**
   * Configuration settings for mb-digest-api.
   * @var array $mbDigestAPIConfig
   */
  private $mbDigestAPIConfig;

  /**
   * Collection of methods to perform cURL activities.
   * @var object $mbToolboxcURL
   */
  private $mbToolboxcURL;

  /**
   * The URL to the mb-digest-api.
   * @var string $curlUrl
   */
  private $curlUrl;

  /**
   *
   */
  public function __init() {

    $this->mbConfig = MB_Configuration::getInstance();
    $this->mbDigestAPIConfig = $this->mbConfig->getProperty('mb_digest_api_config');
    $this->mbToolboxcURL = $this->mbConfig->getProperty('mbToolboxcURL');
    
    $this->curlUrl = $this->mbDigestAPIConfig['host'];
    $port = isset($this->mbDigestAPIConfig['port']) ? $this->mbDigestAPIConfig['port'] : NULL;
    if ($port != 0 && is_numeric($port)) {
      $this->curlUrl .= ':' . (int) $port;
    }
  }
  
  /**
   * campaignGet: Gather (GET) cached campaign object from mb-digest-api.
   *
   * @param integer $nid
   *   The Node ID (nid) of the campaign as defined by the Drupal application.
   * @param string $language
   *   The language of the cache campaign entry. A campaign by NID can have
   *   more than one version by language.
   */
  private function campaignGet($nid, $language) {
    
    $key = 'mb-digest-campaign-' . $nid . '-' . $language;
    $mbDigestAPIUrl = $this->curlUrl . '/api/v1/campaign?key=' . $key;
    $result = $this->mbToolboxcURL->curlGET($mbDigestAPIUrl);

    // Exclude campaigns that don't have details in Drupal API or "Access
    // denied" due to campaign no longer published
    if ($result[1] == 201 && is_object($result[0])) {
      return unseralize($result[0]);
    }
    elseif ($result[1] != 201) {
      throw new Exception('Call to ' . $mbDigestAPIUrl . ' returned ' . $result[1] . ' response.');
    }
  }

  /**
   * campaignSet: POST campaign markup to mb-digest-api for caching.
   *
   * @param object $campaign
   *   A complete campaign object to be cached.
   * @param string $markup
   *   HTML markup of the campaign used to generate email message content.
   */
  protected function campaignSet($campaign) {

    $post = [
      'nid' => $$campaign->nid,
      'language' => $$campaign->language,
      'object' => seralize($campaign)
    ];

    $mbDigestAPIUrl = $this->curlUrl . '/api/v1/campaign';
    $result = $this->mbToolboxcURL->curlPOST($mbDigestAPIUrl, $post);

    if ($result[1] == 200) {
      // $this->statHat->ezCount('', 1);
    }
    else {
      throw new Exception('- ERROR, MBC_DigestEmail_MandrillMessenger->cacheCampaignMarku(): Failed to POST to ' . $mbDigestAPIUrl . ' Returned POST results: ' . print_r($result, TRUE));
    }
  }
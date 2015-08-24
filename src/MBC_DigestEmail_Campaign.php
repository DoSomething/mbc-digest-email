<?php
/**
 * MBC_DigestEmail_Campaign
 * 
 */
namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

/**
 * MBC_DigestEmail_Campaign class - 
 */
class MBC_DigestEmail_Campaign {

  /**
   * Singleton instance of application configuration settings.
   *
   * @var object
   */
   private $mbConfig;

  /**
   * Singleton instance of class used to report usage statistics.
   *
   * @var object
   */
   private $statHat;

  /**
   * A collection of tools used by all of the Message Broker applications.
   *
   * @var object
   */
   private $mbToolbox;

  /**
   *
   *
   * @var
   */
   private $title;

  /**
   * Needs public scope to allow making reference to campaign nid when assigning campaigns
   * to user objects.
   *
   * @var integer
   */
   public $drupal_nid;

  /**
   *
   *
   * @var
   */
   private $is_staff_pick;

  /**
   *
   *
   * @var
   */
   private $url;

  /**
   *
   *
   * @var
   */
   private $image_campaign_cover;

  /**
   *
   *
   * @var
   */
   private $call_to_action;

  /**
   *
   *
   * @var
   */
   private $fact_problem;

  /**
   *
   *
   * @var
   */
   private $latest_news;

  /**
   *
   *
   * @var
   */
   private  $fact_solution;

  /**
   *
   *
   * @var
   */
   private $during_tip_header;

  /**
   *
   *
   * @var
   */
   private $during_tip;

  /**
   *
   *
   * @var
   */
   private $markup;

  /**
   * __construct(): Trigger populating values in Campaign object.
   *
   * @param integer $nid
   *   nid (Drupal node ID) of the campaign content item.
   */
  function __construct($nid) {

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolboxcURL = $this->mbConfig->getProperty('mbToolboxcURL');

    $this->add($nid);
  }

  /**
   * Populate object properties based on campaign lookup on Drupal site.
   *
   *
   */
  private function add($nid) {

    $campaignSettings = $this->gatherSettings($nid);
    if ($campaignSettings == FALSE) {
      return FALSE;
    }

    $this->title = $campaignSettings->title;
    $this->drupal_nid = $campaignSettings->nid;
    $this->is_staff_pick = $campaignSettings->is_staff_pick;
    $this->url = 'http://www.dosomething.org/node/' . $campaignSettings->nid . '#prove';
    $this->image_campaign_cover = $campaignSettings->image_cover->src;
    $this->call_to_action = $campaignSettings->call_to_action;
    $this->fact_problem = $campaignSettings->fact_problem->fact;
    $this->latest_news = $campaignSettings->latest_news_copy;
    $this->during_tip_header = $campaignSettings->step_pre[0]->header;
    $this->during_tip = strip_tags($campaignSettings->step_pre[0]->copy);

    if (isset($campaignSettings->fact_solution->fact)) {
      $this->fact_solution = $campaignSettings->fact_solution->fact;
    }
  }

  /**
   * Gather campaign properties based on campaign lookup on Drupal site.
   *
   * @param integer $nid
   *   The Drupal nid (node ID) of the terget campaign.
   *
   * @return object
   *   The returned results from the call to the campaign endpoint on the Drupal site.
   *   Return boolean FALSE if request is unsuccessful.
   */
  private function gatherSettings($nid) {

    $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');
    $curlUrl = $dsDrupalAPIConfig['host'];
    $port = isset($dsDrupalAPIConfig['port']) ? $dsDrupalAPIConfig['port'] : NULL;
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }

    $campaignAPIUrl = $curlUrl . '/api/v1/content/' . $nid;
    $result = $this->mbToolboxcURL->curlGET($campaignAPIUrl);

    // Exclude campaigns that don't have details in Drupal API or "Access
    // denied" due to campaign no longer published
    if ($result != NULL && (is_array($result) && $result[0] !== FALSE)) {
      return $result[0];
    }
    else {
      return FALSE;
    }
  }
}

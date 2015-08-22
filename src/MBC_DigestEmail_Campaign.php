<?php
/**
 * MBC_DigestEmail_Campaign
 * 
 */
namespace DoSomething\MBC_DigestEmail;

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
   *
   *
   * @var
   */
   private $drupal_nid;

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
   *
   */
  function __construct($nid) {

    $this->add($nid);

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolboxcURL = $this->mbConfig->getProperty('mbToolboxcURL');
  }

  /**
   * Populate object properties based on campaign lookup on Drupal site.
   *
   *
   */
  private function add($nid) {

    $settings = $this->gatherSettings($nid);
    if ($campaignSettings == FALSE) {
      return FALSE;
    }

    $this->title = $setting->title;
    $this->drupal_nid = $setting->nid;
    $this->is_staff_pick = $setting->is_staff_pick;
    $this->url = 'http://www.dosomething.org/node/' . $campaign->nid . '#prove';
    $this->image_campaign_cover = $setting->image_cover->src;
    $this->call_to_action = $setting->call_to_action;
    $this->fact_problem = $setting->fact_problem->fact;
    $this->latest_news = $setting->latest_news_copy;
    $this->fact_solution = $setting->fact_solution->fact;
    $this->during_tip_header = $setting->step_pre[0]->header;
    $this->during_tip = strip_tags($campaign->step_pre[0]->copy);

    $this->generateMarkup();
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
    $curlUrl = $dsDrupalAPIConfig['port'];
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

  /**
   *
   */
  private function generateMarkup() {

    // gater template
    // marge object settings in template merge_var markers

    $this->markup = '';

  }
}

<?php
/**
 * MBC_DigestEmail_User
 * 
 */
namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

/**
 * MBC_DigestEmail_User class - 
 */
class MBC_DigestEmail_User
{

  // Three weeks, 60 seconds x 60 minutes x 24 hours x 3 weeks
  // STD = Seconds To Die
  // What, you were thinking something else for "STD"?!?
  const SUBSCRIPTION_LINK_STD = 1814400;
  const  MAX_CAMPAIGNS = 5;

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
   * The valid email address of the user.
   *
   * @var string
   */
  public $email;

  /**
   * The first name of the user. Defaults to "Doer" when value is not set.
   *
   * @var string
   */
  public $firstName;

  /**
   *
   *
   * @var integer
   */
  protected $drupal_uid;

  /**
   *
   *
   * @var object
   */
  public $subscription_link;

  /**
   *
   *
   * @var object
   */
  public $originalPayload;

  /**
   * The campaigns the user digest message will contain as active and needing report backs.
   *
   * @var array
   */
  public $campaigns = [];

  /**
   * __construct: When a new instance of the class is created it must include an email address. An
   * email address is the minimum value needed to work with Digest User related functionality.
   *
   * @parm string $email (required)
   * 
   * @return boolean
   */
  public function __construct($email) {

    // Validate structure and existing TLD (top level domain) for address
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->email = $email;
    }
    else {
      return FALSE;
    }

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolbox = $this->mbConfig->getProperty('mbToolbox');
  }

  /**
   * setFirstName: Set the user first name.
   */
  public function setFirstName($firstName) {

    $this->firstName = $firstName;
  }

  /**
   * getFirstName: gether user first name. If value does not exist use default user name value.
   *
   * @return string firstName
   */
  public function getFirstName() {

    if (isset($this->firstName)) {
      return $this->firstName;
    }

    $this->firstName = constant(get_class($this->mbToolbox)."::DEFAULT_USERNAME");
    return $this->firstName;
  }

  /**
   *
   */
  public function setLanguage() {

  }

  /**
   *
   */
  public function setDrupalUID($uid) {

    $this->drupal_uid = $uid;
  }

  /**
   * setOrginalPayload(): RabbitMQ message payload used to create the user object. Once the message has been
   * fully processed the orginal message will be achknoledged and removed from the queue. This property will
   * also be unset to complete the process in preperation for a new batch of user objects.
   *
   * @var object $payload
   *   The orginal message consumed from the RabbitMQ queue.
   */
  public function setOrginalPayload($payload) {

    $this->originalPayload = $payload;
  }

  /**
   * addCampaign: Add a campaign nid to a list of campaign nids the user is active in.
   * If the campaign is already on the list the object will be updated.
   *
   * @parm integer nid
   *   Drupal nid (node ID) of the campaign
   * @param array $activity
   *   SIgnup or Reportback with timestamp of when the activity took place.
   */
  public function addCampaign($nid, $activityTimestamp) {
    if (!(isset($this->campaigns[$nid]))) {
      $this->campaigns[$nid] = $activityTimestamp;
    }
  }

  /**
   *
   */
  public function getSubsciptionsURL() {

    // Return cached existing link
    if (isset($this->subscription_link)) {

      // More meaningful functionality when links are stored and retrieved in ds-digest-api as
      // persistant storage between digest runs. Long term storage will result in expired links over time.
      $expiryTimestamp = time() - self::SUBSCRIPTION_LINK_STD;
      if (isset($this->subscription_link->created) && $this->subscription_link->created < date("Y-m-d H:i:s", $expiryTimestamp)) {
        return $this->buildSubscriptionsLink();
      }

      return $this->subscriptions->url;
    }

    return $this->buildSubscriptionsLink();
  }

  /**
   *
   */
  private function buildSubscriptionsLink() {

    $this->subscription_link = new \stdClass();
    $this->subscription_link->url = $this->mbToolbox->subscriptionsLinkGenerator($this->email, $this->drupal_uid);
    $this->subscription_link->created = date('c');

    return $this->subscription_link->url;
  }

  /*
   * processUserCampaigns(): Order the user campaigns by:
   *  - is staff pick
   *    - ordered by user campaign signup
   *  - non staff pick
   *    - ordered by user campaign signup
   *  - limit to maximum 5 campaigns
   */
  public function processUserCampaigns() {

    $staffPicks = array();
    $nonStaffPicks = array();

    foreach ($this->campaigns as $campaignNID => $campaign) {
      if (isset($campaign['settings']->is_staff_pick) && $campaignDetail['settings']->is_staff_pick == TRUE) {
        $staffPicks[$campaignNID] = $campaign;
      }
      else {
        $nonStaffPicks[$campaignNID] = $campaign;
      }
    }

    // Sort staff picks by timestamp
    uasort($staffPicks,
      function($a, $b) {
        if ($a == $b) {
          return 0;
        }
        return ($a < $b) ? 1 : -1;
      }
    );

    // Sort non-staff picks by timestamp
    uasort($nonStaffPicks,
      function($a, $b) {
        if ($a == $b) {
          return 0;
        }
        return ($a < $b) ? 1 : -1;
      }
    );

    // Merge all campaigns, Staff Picks first, Non last.
    $campaigns = $staffPicks + $nonStaffPicks;

    // Limit the number of campaigns in message to MAX_CAMPAIGNS
    if (count($campaigns) > self::MAX_CAMPAIGNS) {
        $campaigns = array_slice($campaigns, 0, self::MAX_CAMPAIGNS, TRUE);
    }

    $this->campaigns = $campaigns;
  }

}
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
   * @var object $mbConfig
   */
   private $mbConfig;

  /**
   * Singleton instance of class used to report usage statistics.
   * @var object $statHat
   */
   private $statHat;

  /**
   * A collection of tools used by all of the Message Broker applications.
   * @var object $mbToolbox
   */
   private $mbToolbox;

  /**
   * The valid email address of the user.
   * @var string $email
   */
  public $email;

  /**
   * The first name of the user. Defaults to "Doer" when value is not set.
   * @var string $firstName
   */
  public $firstName;

  /**
   * User Drupal UID.
   * @var integer $drupal_uid
   */
  protected $drupal_uid;

  /**
   * Markup of the link for user unsubscription requeuests
   * @var object $subscription_link
   */
  public $subscription_link;

  /**
   * The orginal message for reference
   * @var object $originalPayload
   */
  public $originalPayload;

  /**
   * The campaigns the user digest message will contain as active and needing report backs.
   * @var array $campaigns
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
   * Set the user Drupal UID
   *
   * @todo: Lookup Drupal UID by email if the value is not returned in the user document from
   * mb-user-api request. May not be necessary when bug in mb-user-api is resolved that results in several
   * user documents, one of campaign activity while other has user details such as Drupal UID.
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
   * getSubsciptionsURL() - Lookup user unsubscription markup. Request link details if an entry
   * does not already exsit.
   *
   * @return string
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
   * buildSubscriptionsLink() - Create class for user unsubscription link.
   *
   * @todo: Move to concurent process to build subscription object for persistent storage
   * via mb-digest-api.
   *
   * @return string
   */
  private function buildSubscriptionsLink() {

    $this->subscription_link = new \stdClass();
    $this->subscription_link->url = $this->mbToolbox->subscriptionsLinkGenerator($this->email, $this->drupal_uid);
    $this->subscription_link->created = date('c');

    return $this->subscription_link->url;
  }

  /**
   * processUserCampaigns(): Filter, sort and limit the user campaigns.
   */
  public function processUserCampaigns() {

    $campaigns = $this->campaigns;

    $campaigns = $this->filterCampaigns($campaigns);
    $campaigns = $this->sortCampaigns($campaigns);
    $campaigns = $this->limitCampaigns($campaigns);

    $this->campaigns = $campaigns;
  }

  /**
   * Filter campaigns to meet requirments to be included in digest messages.
   *
   * @param array $campaigns
   *   A list of campaigns to apply filters to.
   *
   * @return array
   */
  private function filterCampaigns($campaigns) {

    $filteredCampaigns = [];
    foreach ($campaigns as $campaignNID => $campaign) {

      // Include active campaigns
      if (isset($campaign->status) && $campaign->status == 'active') {
        $filteredCampaigns[] = $campaign;
      }
    }
    $campaigns = $filteredCampaigns;

    return $campaigns;
  }

  /**
   * Order campaigns based on staff pick or regular. Staff Pick first above all regular campaigns.
   *
   * @param array $campaigns
   *   A list of campaigns to process.
   *
   * @return array
   */
  private function sortCampaigns($campaigns) {

    $staffPicks = array();
    $nonStaffPicks = array();

    foreach ($campaigns as $campaignNID => $campaign) {

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

    return $campaigns;
  }

  /**
   * Filter campaigns based on business rules.
   *
   * @param array $campaigns
   *   A list of campaign objects to filter.
   *
   * @return array
   */
  private function limitCampaigns($campaigns) {

    // Limit the number of campaigns in message to MAX_CAMPAIGNS
    if (count($campaigns) > self::MAX_CAMPAIGNS) {
        $campaigns = array_slice($campaigns, 0, self::MAX_CAMPAIGNS, TRUE);
    }

    return $campaigns;
  }

}
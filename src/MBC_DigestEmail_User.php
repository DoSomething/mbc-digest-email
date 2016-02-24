<?php
/**
 * MBC_DigestEmail_User - Digest message content is generate specific to each users campaign activity.
 *
 * A user object contains the properties and methods to define who a user is. These settings are used
 * by other classes to create the digigest message specific to the user object.
 */
namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

/**
 * MBC_DigestEmail_User class - Properties and methods used to define a user.
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
   * setLanguage(): The language that the digest message "chrome"should be presented in.
   *
   * @var string $source
   *   The source registration site for the user registration defines the language preference of the user.
   */
  public function setLanguage($source) {

    $this->language = $source;
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
      $nids = (string) $nid;
      $this->campaigns[$nids] = (string) $activityTimestamp;
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
   *
   * @param array $campaigns
   *   List of campaign objects with details about each campaign
   */
  public function processUserCampaigns($campaigns) {

    $userCampaigns = $this->campaigns;

    $userCampaigns = $this->filterCampaigns($userCampaigns, $campaigns);
    $userCampaigns = $this->sortCampaigns($userCampaigns, $campaigns);
    $userCampaigns = $this->limitCampaigns($userCampaigns);

    $this->campaigns = $userCampaigns;
  }

  /**
   * Filter campaigns to meet requirements for including a campaign in user digest message.
   *
   * @param array $userCampaigns
   *   A list of user campaigns to apply filters to.
   * @param array $campaigns
   *   List of campaign objects
   *
   * @return array
   */
  private function filterCampaigns($userCampaigns, $campaigns) {

    $filteredUserCampaigns = [];
    foreach ($userCampaigns as $userCampaignNID => $userCampaign) {

      // Prevent users seeing a campaign in their digest more than five times. Exclude campaigns
      // that the user has signed up for over five weeks in the past based on the digest message
      // being sent weekly.
      if ($userCampaign < strtotime("-5 week")) {
        continue;
      }

      // Exclude inactive campaigns
      if (empty($campaigns[$userCampaignNID]->status) || $campaigns[$userCampaignNID]->status != 'active') {
        continue;
      }

      $filteredUserCampaigns[$userCampaignNID] = $userCampaign;
    }

    return $filteredUserCampaigns;
  }

  /**
   * Order campaigns based on staff pick or regular. Staff Pick first above all regular campaigns.
   *
   * @param array $userCampaigns
   *   A list of user campaigns to process.
   * @param array $campaigns
   *   List of campaign objects
   *
   * @return array
   */
  private function sortCampaigns($userCampaigns, $campaigns) {

    $staffPicks = array();
    $nonStaffPicks = array();

    foreach ($userCampaigns as $userCampaignNID => $userCampaign) {

      if (isset($campaigns[$userCampaignNID]->is_staff_pick) && $campaigns[$userCampaignNID]->is_staff_pick == TRUE) {
        $staffPicks[$userCampaignNID] = $userCampaign;
      }
      else {
        $nonStaffPicks[$userCampaignNID] = $userCampaign;
      }
    }

    uasort($staffPicks, array($this, 'sortCampaignsEngine'));
    uasort($nonStaffPicks, array($this, 'sortCampaignsEngine'));

    // Merge all campaigns, Staff Picks first, Non last.
    $userCampaigns = $staffPicks + $nonStaffPicks;

    return $userCampaigns;
  }

  /*
   * sortCampaignsEngine() - Callback function for sorting campaigns.
   *
   * @param integer $a
   *   First item to compare.
   * @param integer $b
   *   Second item to campare gainst.
   *
   * @return integer
   */
  private function sortCampaignsEngine($a, $b) {

    if ($a == $b) {
      return 0;
    }

    if ($a < $b) {
      return 1;
    }
    else {
      return -1;
    }
  }

  /**
   * Filter campaigns based on business rules.
   *
   * @param array $campaigns
   *   A list of campaign objects to filter.
   *
   * @return array
   */
  private function limitCampaigns($userCampaigns) {

    // Limit the number of campaigns in message to MAX_CAMPAIGNS
    if (count($userCampaigns) > self::MAX_CAMPAIGNS) {
        $userCampaigns = array_slice($userCampaigns, 0, self::MAX_CAMPAIGNS, TRUE);
    }

    return $userCampaigns;
  }

}

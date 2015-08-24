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
  const SUBSCRIPTION_LINK_STL = 1814400;

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
  protected $email;

  /**
   * The first name of the user. Defaults to "Doer" when value is not set.
   *
   * @var string
   */
  protected $firstName;

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
  public function setDrupalUID() {

  }

  /**
   * RabbitMQ message ID used to create the user object. Once the message has been fully processed
   * the orginal message will be achknoledged and removed from the queue. This property will also
   * be unset.
   *
   * @var object $message
   *   The orginal message consumed from the RabbitMQ queue.
   */
  public function setMessageID($message) {

    $this->messageID = $message->delivery_info['delivery_tag'];
  }

  /**
   * addCampaign: Add a campaign nid to a list of campaign nids the user is active in.
   * If the campaign is already on the list the object will be updated.
   *
   * @parm object campaign
   */
  public function addCampaign(MBC_DigestEmail_Campaign $campaign) {

    $this->campaigns[$campaign->drupal_nid] = $campaign;
  }

  /**
   *
   */
  public function getSubsciptionsURL() {

    // Return cached existing link
    if (isset($this->subscriptions)) {

      // More meaningful functionality when links are stored and retrieved in ds-digest-api as
      // persistant storage between digest runs. Long term storage will result in expired links over time.
      $expiryTimestamp = time() - self::SUBSCRIPTION_LINK_STD;
      if (isset($this->subscriptions['created']) && $this->subscriptions['created'] < date("Y-m-d H:i:s", $expiryTimestamp)) {
        return $this->buildSubscriptionsLink();
      }

      return $this->subscriptions['url'];
    }

    return $this->buildSubscriptionsLink();
  }

  /**
   *
   */
  private function buildSubscriptionsLink() {

    $this->subscriptions['url'] = $this->MB_Toolbox->getUnsubscribeLink($this->email, $this->drupal_uid);
    $this->subscriptions['created'] = date('c');

    return $this->subscriptions['url'];
  }

}
<?php
/**
 * MBC_DigestEmail_User
 * 
 */
namespace DoSomething\MBC_DigestEmail;

/**
 * MBC_DigestEmail_User class - 
 */
class MBC_DigestEmail_User
{

  // Three weeks, 60 seconds x 60 minutes x 24 hours x 3 weeks
  const SUBSCRIPTION_LINK_STL = 1814400;

  /**
   * __construct: When a new instance of the class is created it must include an email address. An
   * email address is the minimum value needed to work with Digest User related functionality.
   *
   * @parm string $email
   * 
   * 
   */
  public function __construct($email) {

    // Validate structure and existing TLD (top level domain) for address
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->email = $email;
      return TRUE;
    }
    else {
      return FALSE;
    }

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolbox = $this->mbConfig->getProperty('mbToolbox');
  }

  /**
   * setFirstName:
   */
  public function setFirstName($firstName) {

    if ($firstName == NULL) {
      $this->firstName = constant(get_class($this->mbToolbox)."::DEFAULT_USERNAME");
    }
    else {
      $this->firstName = $firstName;
    }
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
   *
   */
  public function addCampaign() {

  }

  /**
   *
   */
  public function getSubsciptionsURL() {
    
    // Return cached existing link
    if (isset($this->subscriptions)) {
      
      // More meaningful functionality when links are stored and retrieved in ds-digest-api as
      // persistant storage between digest runs. Long term storage will result in expired links over time.
      $expiryTimestamp = time() - self::SUBSCRIPTION_LINK_STL;
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
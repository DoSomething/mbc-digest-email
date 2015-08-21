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
   *
   */
  public function __construct() {
    
    
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
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
   */
  function __construct($nid) {

    $this->add($nid);

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolbox = $this->mbConfig->getProperty('mbToolbox');
  }

  /**
   *
   */
  private function add() {

  }

}
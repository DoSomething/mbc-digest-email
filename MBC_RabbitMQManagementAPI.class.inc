<?php

use DoSomething\MB_Toolbox\MB_Toolbox;
// use DoSomething\StatHat\Client
use DoSomething\MBStatTracker\StatHat;
use RabbitMq\ManagementApi\Client;

/**
 * MBC_UserRegistration class - functionality related to the Message Broker
 * consumer mbc-registration-email.
 */
class MBC_RabbitMQManagementAPI
{

  /**
   * Collection of secret connection settings.
   *
   * @var array
   */
  private $credentials;

  /**
   * Collection of helper methods
   *
   * @var object
   */
  private $toolbox;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $statHat;
  
  /**
   * RabbitMQ Management API.
   *
   * @var array
   */
  private $rabbitManagement;

  /**
   * Constructor for MBC_UserDigest
   *
   * @param array $credentials
   *   Secret settings from mb-secure-config.inc
   *
   * @param array $config
   *   Configuration settings from mb-config.inc
   *
   * @param array $settings
   *   Settings from external services - Mailchimp
   */
  public function __construct($credentials) {
    
    $bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

    $this->config = $config;
    $this->credentials = $credentials;
    $this->settings = $settings;
    
    $this->rabbitManagement = new Client(NULL, 'http://10.241.0.27:15672', 'dosomething', 'Kickba11');

  }

  /**
   * Determine the next temporary digest queue that needs processing.
   */
  public function nextQueue($exchangeName) {
    
    $bla = FALSE;
if ($bla) {
  $bla = TRUE;
}
    $queues = $this->rabbitManagement->exchanges()->get('dosomething', 'directUserDigestExchange');

  }

}

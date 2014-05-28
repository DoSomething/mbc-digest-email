<?php

use DoSomething\MBStatTracker\StatHat;

/**
 * MBC_UserRegistration class - functionality related to the Message Broker
 * consumer mbc-registration-email.
 */
class MBC_UserDigest
{

  /**
   * Message Broker object that details the connection to RabbitMQ.
   *
   * @var object
   */
  private $messageBroker;

  /**
   * Details of the channel connection in use by RabbitMQ.
   *
   * @var object
   */
  private $channel;

  /**
   * Collection of configuration settings.
   *
   * @var array
   */
  private $config;

  /**
   * Collection of secret connection settings.
   *
   * @var array
   */
  private $credentials;
  
  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $settings;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $statHat;

  /**
   * The number of queue entries to process in each session
   */
  const BATCH_SIZE = 100;

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
  public function __construct($credentials, $config, $settings) {

    $this->config = $config;
    $this->credentials = $credentials;
    $this->settings = $settings;

    // Setup RabbitMQ connection
    $this->messageBroker = new MessageBroker($credentials, $config);

    $connection = $this->messageBroker->connection;
    $this->channel = $connection->channel();

    // Stathat
    $this->statHat = new StatHat($this->settings['stathat_ez_key'], 'mbc-digest-email:');
    $this->statHat->setIsProduction(TRUE);
  }

  /**
   * Collect a batch of email address for submission to MailChimp from the
   * related RabbitMQ queue.
   *
   * @return array
   *   An array of the status of the job
   */
  public function consumeUserDigestQueue() {
    // Get the status details of the queue by requesting a declare
    list($this->channel, $status) = $this->messageBroker->setupQueue($this->config['queue'][0]['name'], $this->channel);

    $messageCount = $status[1];
    // @todo: Respond to unacknowledged messages
    $unackedCount = $status[2];

    $messageDetails = '';
    $newDigests = array();
    $processedCount = 0;

    while ($messageCount > 0 && $processedCount < self::BATCH_SIZE) {
      $messageDetails = $this->channel->basic_get($this->config['queue'][0]['name']);
      $messagePayload = unserialize($messageDetails->body);
      $newDigests[] = array(
        'email' => $messagePayload['email'],
        'fname' => $messagePayload['merge_vars']['FNAME'],
        'uid' => $messagePayload['uid'],
        'birthdate' => $messagePayload['birthdate'],
        'mobile' => isset($messagePayload['mobile']) ? $messagePayload['mobile'] : '',
        'mb_delivery_tag' => $messageDetails->delivery_info['delivery_tag'],
      );
      $messageCount--;
      $processedCount++;
    }

    $campaignDetails = $this->getCampaignDetails();
    list($composedDigestList, $mbDeliveryTags) = $this->composeDigestSubmissions($newDigests);
    if (count($composedDigestList) > 0) {
      $this->submitToMandrill($composedDigestList, $campaignDetails, $mbDeliveryTags);
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('consumeUserDigestQueue');
      $this->statHat->reportCount($processedCount);
    }
    else {
      $results = 'No new digest message to submit to Mandrill.';
    }

    return $results;

  }

  /**
   * Collect active campaign details from Campaigns API.
   *
   * @return array $campaignDetails
   *   Details of all the active campaigns
   */
  private function getCampaignDetails() {
    
    
    return $campaignDetails;
    
  }
  /**
   * Construct digest submissions based on the Mandrill API send-template
   * details.
   *
   * @return array
   *   Details of all the active campaigns
   */
  private function composeDigestSubmissions($newDigests) {
    
    
    
    return array($composedDigestList, $mbDeliveryTags);
    
  }
  
  /**
   * Compose and send digest message submission to Mandrill.
   *
   * @param array composedDigestList
   *   
   */
  private function submitToMandrill($composedDigestList, $campaignDetails, $mbDeliveryTags) {
    
  }
  
  
}
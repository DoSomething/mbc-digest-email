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
   * Controller for Digest message processing.
   */
  public function generateDigests() {

    $targetUsers = $this->consumeUserDigestQueue();

    if ($targetUsers) {

      // Remove campaign_signups that have a matching report_back
      list($targetUsers, $targetCampaigns) = $this->processDigestCampaignActivity($targetUsers);

      // Collect active campaign details
      $campaignDetails = $this->getCampaignDetails($targetCampaigns);

      // Build gloabal_merge_var values that include campaign markup
      $globalMergeVars = $this->composeGlobalMergeVars($campaignDetails);

      $mergeMars = $this->ComposeMergeVars($targetUsers);

      // Assemble and send Mandrill digest message submission
      $mergeVars = $this->composeDigestSubmission($mergeMars, $globalMergeVars);

      $this->submitToMandrill($composedDigestList, $campaignDetails, $mbDeliveryTags);

    }
    else {
      echo '------- mbc-user-digest processDigests: No entries in userDigestQueue -  ' . date('D M j G:i:s T Y') . ' -------', "\n";
    }

  }

  /**
   * Collect a batch of email address for submission to MailChimp from the
   * related RabbitMQ queue.
   *
   * @return array
   *   An array of the status of the job
   */
  private function consumeUserDigestQueue() {
    // Get the status details of the queue by requesting a declare
    list($this->channel, $status) = $this->messageBroker->setupQueue($this->config['queue'][0]['name'], $this->channel);

    $messageCount = $status[1];
    // @todo: Respond to unacknowledged messages
    $unackedCount = $status[2];

    $messageDetails = '';
    $targetUsers = array();
    $processedCount = 0;

    while ($messageCount > 0 && $processedCount < self::BATCH_SIZE) {
      $messageDetails = $this->channel->basic_get($this->config['queue'][0]['name']);
      $messagePayload = json_decode($messageDetails->body);
      $targetUsers[] = array(
        'email' => $messagePayload['email'],
        'fname' => $messagePayload['merge_vars']['FNAME'],
        'uid' => $messagePayload['uid'],
        'campaigns' => $messagePayload['campaigns']
      );
      $messageCount--;
      $processedCount++;
    }

    if (count($targetUsers) > 0) {
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('consumeUserDigestQueue');
      $this->statHat->reportCount($processedCount);
      return $targetUsers;
    }
    else {
      $results = FALSE;
    }

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
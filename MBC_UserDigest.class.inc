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
    $this->statHat->setIsProduction(FALSE);
  }

  /**
   * Controller for Digest message processing.
   */
  public function generateDigests() {
    

$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

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
      $targetUsers[$processedCount] = array(
        'email' => $messagePayload->email,
        'fname' => $messagePayload->merge_vars->FNAME,
        'campaigns' => $messagePayload->campaigns
      );
      if (isset($messagePayload->drupal_uid)) {
        $targetUsers[$processedCount]['drupal_uid'] = $messagePayload->drupal_uid;
      }
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
   * Process targetUsers entries to remove campaign activities that also have a
   * report back entry.
   *
   * @param array $targetUsers
   *   All of the users and their campaign activity to proess.
   *
   * @return array $targetUsers
   *   The updated list with entries with report backs removed.
   *
   * @return array $targetCampaigns
   *   All of the campaigns referenced in $targetUsers.
   */
  private function processDigestCampaignActivity($targetUsers) {

    $targetCampaigns = array();

    $processedTargetUsers = $targetUsers;
    foreach ($targetUsers as $targetUserIndex => $targetUser) {

      // Remove signups that have matching reportback entries
      $targetUserCampaigns = $targetUser['campaigns'];
      foreach($targetUser['campaigns'] as $campaignActivityIndex => $campaignActivity) {
        if (isset($campaignActivity->reportback)) {
          unset($targetUserCampaigns[$campaignActivityIndex]);
        }
        else {
          $targetCampaigns[$campaignActivity->nid] = $campaignActivity->nid;
        }
      }

      // Remove user that does not have campaign signups
      if (count($targetUserCampaigns) > 0) {
        $processedTargetUsers[$targetUserIndex]['campaigns'] = $targetUserCampaigns;
      }
      else {
        unset($processedTargetUsers[$targetUserIndex]);
      }

    }

    return array($processedTargetUsers, $targetCampaigns);
  }

  /**
   * Collect active campaign details from Campaigns API.
   *
   * @param array $targetCampaigns
   *   The drupal_nid s of the campaigns that the digest messages need details
   *   about in order to build the message contents.
   *
   * @return array $campaignDetails
   *   Details of all the active campaigns
   */
  private function getCampaignDetails($targetCampaigns) {

    $campaignDetails = array();
    foreach ($targetCampaigns as $targetCampaign) {

      $campaignApiUrl = getenv('DS_CAMPAIGNS_API_HOST') . ':' . getenv('DS_CAMPAIGNS_API_PORT') . '/campaign?nid=' . $targetCampaign;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $campaignApiUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $jsonResult = curl_exec($ch);
      curl_close($ch);
      $returned = json_decode($jsonResult);
      $campaignDetails[] = $returned->results;

    }

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
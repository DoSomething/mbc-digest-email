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

      // Build merge_var and gloabal_merge_var values
      $to = $this->composeTo($targetUsers);
      $globalMergeVars = $this->composeGlobalMergeVars($campaignDetails);
      $mergeMars = $this->composeMergeVars($targetUsers);

      // Assemble and send Mandrill digest message submission
      $mergeVars = $this->composeDigestSubmission($to, $mergeMars, $globalMergeVars);

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
    
    
$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

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
    
    
$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

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
    
    
$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

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
   * "merge_vars": [
   *   {
   *     "rcpt": "recipient.email@example.com",
   *     "vars": [
   *       {
   *         "name": "merge2",
   *         "content": "merge2 content"
   *       }
   *     ]
   *   }
   * ],
   *
   * @param array $to
   *   The drupal_nid s of the campaigns that the digest messages need details
   *   about in order to build the message contents.
   *
   * @return array
   *   Details of all the active campaigns
   */
  private function composeTo($targetUsers) {
    
  }

  /**
   * Process campaign details into the Mandrill send-template
   * golbal_merge_vars format.
   *
   * "global_merge_vars": [
   *   {
   *     "name": "merge1",
   *     "content": "merge1 content"
   *   }
   * ],
   *
   * @param array $campaignDetails
   *   All of the details of each campaign that will be used to build the
   *   global_merge_var entry for each campaign.
   *
   * @return array $globalMergeVars
   *   All of the campaigns in Mandrill global_merge_var format.
   */
  private function composeGlobalMergeVars($campaignDetails) {

$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

    $globalMergeVars = array();
    foreach ($campaignDetails as $campaignDetailsCount => $campaign) {
      $campaignMarkup = buildCampaignMarkup($campaign);
      $globalMergeVars[] = array(
        'name' => 'campaign-' . $campaign['nid'],
        'content' => $campaignMarkup,
      );
    }

    return $globalMergeVars;
  }

  /**
   * Assemble HTML markup string by combining general HTML markup with specific
   * values for a campaign.
   *
   * @param array $campaign
   *   Details of a campaign to be combined with the markup.
   *
   * @return string $campaignMarkup
   *   Composed campaign markup.
   */
  private function buildCampaignMarkup($campaign) {

$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

    $campaignMarkup = file_get_contents(__DIR__ . 'campaign-markup.inc');
    $campaignMarkup = str_replace('*|CAMPAIGN_IMAGE_URL|*', $campaign['CAMPAIGN_IMAGE_URL'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_TITLE|*', $campaign['CAMPAIGN_TITLE'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_CALL_TO_ACTION|*', $campaign['CAMPAIGN_CALL_TO_ACTION'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_PROBLEM_FACT|*', $campaign['CAMPAIGN_PROBLEM_FACT'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_SOLUTION_FACT|*', $campaign['CAMPAIGN_SOLUTION_FACT'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_LINK|*', $campaign['CAMPAIGN_LINK'], $campaignMarkup);

    return $campaignMarkup;
  }
  
  /**
   * Construct digest submissions based on the Mandrill API send-template
   * details.
   *
   * "merge_vars": [
   *   {
   *     "rcpt": "recipient.email@example.com",
   *     "vars": [
   *       {
   *         "name": "merge2",
   *         "content": "merge2 content"
   *       }
   *     ]
   *   }
   * ],
   *
   * @param array $targetCampaigns
   *   The drupal_nid s of the campaigns that the digest messages need details
   *   about in order to build the message contents.
   *
   * @return array
   *   Details of all the active campaigns
   */
  private function composeMergeVars($targetUsers) {

$bla = FALSE;
if ($bla) {
  $bla = TRUE;
}

    $mergeVars = array();
    foreach ($targetUsers as $targetUser) {
      foreach ($targetUser['campaigns'] as $campaign) {
        $targetUser['CAMPAIGNS'] .= 'campaign-' . $campaign['nid'] . "\n";
      }
      $mergeVars[] = array(
        'rcpt' => $targetUser['email'],
        'vars' => array(
          0 => array(
            'name' => 'FNAME',
            'content' => $targetUser['FNAME'],
          ),
          1 => array(
            'name' => 'CAMPAIGNS',
            'content' => $targetUser['CAMPAIGNS'],
          ),
        )
      );
    }

    return $mergeVars;
  }
  
  /**
   * Assemble digest message request for Mandrill.
   *
   * @param array $to
   *   ??
   *   
   */
  private function composeDigestSubmission($to, $mergeMars, $globalMergeVars) {
    
    /*
     
        "message": {
        "html": "<p>Example HTML content</p>",
        "text": "Example text content",
        "subject": "example subject",
        "from_email": "message.from_email@example.com",
        "from_name": "Example Name",
        "to": [
            {
                "email": "recipient.email@example.com",
                "name": "Recipient Name",
                "type": "to"
            }
        ],
        "headers": {
            "Reply-To": "message.reply@example.com"
        },
        "important": false,
        "track_opens": null,
        "track_clicks": null,
        "auto_text": null,
        "auto_html": null,
        "inline_css": null,
        "url_strip_qs": null,
        "preserve_recipients": null,
        "view_content_link": null,
        "bcc_address": "message.bcc_address@example.com",
        "tracking_domain": null,
        "signing_domain": null,
        "return_path_domain": null,
        "merge": true,
        "global_merge_vars": [
            {
                "name": "merge1",
                "content": "merge1 content"
            }
        ],
        "merge_vars": [
            {
                "rcpt": "recipient.email@example.com",
                "vars": [
                    {
                        "name": "merge2",
                        "content": "merge2 content"
                    }
                ]
            }
        ],
        "tags": [
            "password-resets"
        ],
        "subaccount": "customer-123",
        "google_analytics_domains": [
            "example.com"
        ],
        "google_analytics_campaign": "message.from_email@example.com",
        "metadata": {
            "website": "www.example.com"
        },
        "recipient_metadata": [
            {
                "rcpt": "recipient.email@example.com",
                "values": {
                    "user_id": 123456
                }
            }
        ],
        "attachments": [
            {
                "type": "text/plain",
                "name": "myfile.txt",
                "content": "ZXhhbXBsZSBmaWxl"
            }
        ],
        "images": [
            {
                "type": "image/png",
                "name": "IMAGECID",
                "content": "ZXhhbXBsZSBmaWxl"
            }
        ]
    },
    
    */
    
    $composedDigestSubmission = array(
      'subject' => '',
      'from_email' => '',
      'from_name' => '',
      'to' => $to,
      
      
    );
    
    return $composedDigestSubmission;
  }
  
  /**
   * Send digest message request to Mandrill.
   *
   * @param array composedDigestList
   *   
   */
  private function submitToMandrill($campaignDetails, $mbDeliveryTags) {
    
    // Use the Mandrill service
    $mandrill = new Mandrill();
    $templateName = 'mb-digest';
    
    // Must be included in submission but is kept blank as the teamplate contents
    // are managed through the Mailchip/Mandril WYSIWYG interface.
    $message = '';

    // Send message
    $mandrillResults = $mandrill->messages->sendTemplate($templateName, $composedDigestSubmission, $message);
    
  }
  
}
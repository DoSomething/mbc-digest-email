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
   * Collection of helper methods
   *
   * @var object
   */
  private $toolbox;

  /**
   * The MEMBER_COUNT value from the DoSomething.org API via MB_Toolbox
   *
   * @var string
   */
  private $memberCount;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $statHat;

  /**
   * The number of queue entries to process in each session
   */
  const BATCH_SIZE = 500;
  const MAX_CAMPAIGNS = 5;

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

    $this->toolbox = new MB_Toolbox($settings);
    $this->memberCount = $this->toolbox->getDSMemberCount();

    $this->statHat = new StatHat($this->settings['stathat_ez_key'], 'mbc-digest-email:');
    $this->statHat->setIsProduction(TRUE);
  }

  /**
   * Controller for Digest message processing.
   */
  public function generateDigests() {

    list($targetUsers, $deliveryTags) = $this->consumeUserDigestQueue();

    if ($targetUsers) {

      // Remove campaign_signups that have a matching report_back
      list($targetUsers, $targetCampaigns) = $this->processDigestCampaignActivity($targetUsers);

      // Collect active campaign details
      $campaigns = $this->getCampaignDetails($targetCampaigns);
      $campaignDetails = $this->gatherCampaigns($campaigns);

      // Only generate digest message if active / valid campaign details exsist
      if (count($campaignDetails) > 0) {

        // Build merge_var and gloabal_merge_var values
        $globalMergeVars = $this->composeGlobalMergeVars($campaignDetails);
        $targetUsers = $this->filterCampaigns($targetUsers, $campaignDetails, $globalMergeVars);
        list($mergeVars, $targetUsers) = $this->composeMergeVars($targetUsers, $globalMergeVars);
        $to = $this->composeTo($targetUsers);

        // Assemble and send Mandrill digest message submission
        $composedDigestSubmission = $this->composeDigestSubmission($to, $mergeVars, $globalMergeVars);

        $mandrillResults = $this->submitToMandrill($composedDigestSubmission);

      }

    }
    else {
      echo '------- mbc-digest-email generateDigests: No entries in userDigestQueue -  ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
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
    $deliveryTags = array();
    $targetUsers = array();
    $processedCount = 0;

    while ($messageCount > 0 && $processedCount < self::BATCH_SIZE) {

      $messageDetails = $this->channel->basic_get($this->config['queue'][0]['name']);

      // @todo: The channel details are missing when messageBroker->sendAck is
      // called. Need to restore sendAck functionality in messagebroker-phplib
      // $this->messageBroker->sendAck($messageDetails);
      $this->channel->basic_ack($messageDetails->delivery_info['delivery_tag']);

      $messagePayload = json_decode($messageDetails->body);
      $targetUsers[$processedCount] = array(
        'email' => $messagePayload->email,
        'fname' => $messagePayload->merge_vars->FNAME,
        'campaigns' => $messagePayload->campaigns,
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
      return array($targetUsers, $deliveryTags);
    }
    else {
      echo '------- mbc-digest-email MBC_UserDigest->consumeUserDigestQueue() - Queue is empty. -  ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
    }

  }

  /**
   * Process targetUsers entries to remove campaign activities that also have a
   * report back entry.
   *
   * @param array $targetUsers
   *   All of the users and their campaign activity to process.
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
    // @todo: Move all campaign processing in this loop to separate function
    foreach ($targetUsers as $targetUserIndex => $targetUser) {
      $targetUserCampaigns = $targetUser['campaigns'];

      // Remove signups that have matching report back entries
      foreach($targetUser['campaigns'] as $campaignActivityIndex => $campaignActivity) {
        if (isset($campaignActivity->reportback)) {
          unset($targetUserCampaigns[$campaignActivityIndex]);
        }
        else {
          $targetCampaigns[$campaignActivity->nid] = $campaignActivity->nid;
        }
      }

      // Only include users that have campaign signups
      if (count($targetUserCampaigns) > 0) {
        $processedTargetUsers[$targetUserIndex]['campaigns'] = $targetUserCampaigns;
      }
      else {
        unset($processedTargetUsers[$targetUserIndex]);
      }

    }

    echo '------- mbc-digest-email MBC_UserDigest->processDigestCampaignActivity() - processedTargetUsers: ' . count($processedTargetUsers) . ' - targetCampaigns ' . count($targetCampaigns) . ' - ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

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
    $ch = curl_init();
    foreach ($targetCampaigns as $targetCampaign) {

      $campaignApiUrl = getenv('DS_DRUPAL_API_HOST') . '/api/v1/content/' . $targetCampaign;

      curl_setopt($ch, CURLOPT_URL, $campaignApiUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-type: application/json',
        'Accept: application/json'
      ));
      $jsonResult = curl_exec($ch);
      $result = json_decode($jsonResult);

      // Exclude campaigns that don't have cached details in Campaign API
      if ($result != NULL) {
        $campaignDetails[] = $result;
      }

    }
    curl_close($ch);

    return $campaignDetails;
  }

  /**
   * Process campaigns, remove based on digest content rules.
   *
   * @param array $campaigns
   *   Details of each campaign collected from the Drupal campaign API.
   *
   * @return array $campaignDetails
   *   Details of all the active campaigns
   */
  private function gatherCampaigns($campaigns) {

    $campaignCount = 0;
    $campaignDetails = array();
    foreach ($campaigns as $campaign) {
      if ((isset($campaign->status) && $campaign->status == 'active') &&
          (isset($campaign->type) && $campaign->type == 'campaign') &&
          (isset($campaign->image_cover->src))) {

        $campaignDetails[$campaignCount] = array(
          'drupal_nid' => $campaign->nid,
          'title' => $campaign->title,
          'is_staff_pick' => $campaign->is_staff_pick,
          'url' => 'http://www.dosomething.org/node/' . $campaign->nid . '#prove',
          'image_campaign_cover' => $campaign->image_cover->src,
          'high_season_start' => $campaign->high_season_start,
          'high_season_end' => $campaign->high_season_end,
          'low_season_start' => $campaign->low_season_start,
          'low_season_end' => $campaign->low_season_end,
          'starter_statement' => $campaign->starter_statement,
          'solution_copy' => $campaign->solution_copy,
          'solution_support' => $campaign->solution_support,
          'call_to_action' => $campaign->call_to_action,
          'fact_problem' => $campaign->fact_problem->fact,
        );

        if (isset($campaign->image_header->src)) {
          $campaignDetails[$campaignCount]['image_campaign_header'] = $campaign->image_header->src;
        }
        if (isset($campaign->fact_solution->fact)) {
          $campaignDetails[$campaignCount]['fact_solution'] = $campaign->fact_solution->fact;
        }
        if (isset($campaign->step_pre[0]->header)) {
          $campaignDetails[$campaignCount]['during_tip_header'] = $campaign->step_pre[0]->header;
        }
        if (isset($campaign->step_pre[0]->copy)) {
          $campaignDetails[$campaignCount]['during_tip'] = strip_tags($campaign->step_pre[0]->copy);
        }

        $campaignCount++;
      }

    }

    return $campaignDetails;
  }

  /**
   * Construct $to array based on Mandrill send-template API specification.
   *
   * https://mandrillapp.com/api/docs/messages.JSON.html#method=send-template
   * "to": [
   *   {
   *     "email": "recipient.email@example.com",
   *     "name": "Recipient Name",
   *     "type": "to"
   *   }
   * ],
   *
   * "type": "to" - the header type to use for the recipient, defaults to "to"
   * if not provided oneof(to, cc, bcc)
   *
   * @param array $targetUsers
   *   Details about user to send digest message to.
   *
   * @return array $to
   *   $to in Mandrill API structure.
   */
  private function composeTo($targetUsers) {

    foreach ($targetUsers as $targetUser) {
      $to[] = array(
        'email' => $targetUser['email'],
        'name' => $targetUser['fname'],
        'to' => 'cc',
      );
    }

    return $to;
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

    $globalMergeVars = array();
    foreach ($campaignDetails as $campaignDetailsCount => $campaign) {
      $globalMergeVars[$campaign['drupal_nid']] = array(
        'name' => 'campaign-' . $campaign['drupal_nid'],
        'content' => $this->buildCampaignMarkup($campaign),
      );
    }

    // Dynamic member count in footer of message
    $globalMergeVars[0] = array(
      'name' => 'MEMBER_COUNT',
      'content' => $this->memberCount,
    );

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

    $campaignMarkup = file_get_contents(__DIR__ . '/campaign-markup.inc');

    $campaignMarkup = str_replace('*|CAMPAIGN_IMAGE_URL|*', $campaign['image_campaign_cover'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_TITLE|*', $campaign['title'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CAMPAIGN_LINK|*', $campaign['url'], $campaignMarkup);

    if (!isset($campaign['do_it_title'])) {
      $campaign['do_it_title'] = '';
    }
    if (!isset($campaign['do_it_body'])) {
      $campaign['do_it_body'] = '';
    }
    if (!isset($campaign['fact_problem'])) {
      $campaign['fact_problem'] = '';
    }
    if (!isset($campaign['fact_solution'])) {
      $campaign['fact_solution'] = '';
    }
    if (!isset($campaign['call_to_action'])) {
      $campaign['call_to_action'] = '';
    }
    if (!isset($campaign['during_tip_header'])) {
      $campaign['during_tip_header'] = '';
    }
    $campaign['tip_title'] = 'Tip from the team:';
    if (!isset($campaign['during_tip'])) {
      $campaign['tip_title'] = '';
      $campaign['during_tip'] = '';
    }

    $campaignMarkup = str_replace('*|DO_IT_TITLE|*', $campaign['do_it_title'], $campaignMarkup);
    $campaignMarkup = str_replace('*|DO_IT_BODY|*', $campaign['do_it_body'], $campaignMarkup);
    $campaignMarkup = str_replace('*|FACT_PROBLEM|*', $campaign['fact_problem'], $campaignMarkup);
    $campaignMarkup = str_replace('*|FACT_SOLUTION|*', $campaign['fact_solution'], $campaignMarkup);
    $campaignMarkup = str_replace('*|CALL_TO_ACTION|*', $campaign['call_to_action'], $campaignMarkup);
    $campaignMarkup = str_replace('*|DURING_TIP_HEADER|*', $campaign['during_tip_header'], $campaignMarkup);
    $campaignMarkup = str_replace('*|DURING_TIP|*',  $campaign['during_tip'], $campaignMarkup);
    $campaignMarkup = str_replace('*|TIP_TITLE|*',  $campaign['tip_title'], $campaignMarkup);

    // @todo: 
    // - Add Google Analitics

    return $campaignMarkup;
  }

  /**
   * Filter the order in which campaigns are presented.
   *
   * @param array $targetUsers
   *   User details including the campaigns they're signed up for.
   *
   * @param array $campaignDetails
   *   User details including the campaigns they're signed up for.
   *
   * @param array $globalMergeVars
   *   Details about specific .
   *
   * @return array $targetUsers
   *   The filter list of the user and their campaigns based on the filter rules.
   */
  private function filterCampaigns($targetUsers, $campaignDetails, $globalMergeVars) {

    foreach ($targetUsers as $targetUserIndex => $targetUser) {
      $targetUserCampaigns = $targetUser['campaigns'];

      $staffPicks = array();
      $nonStaffPicks = array();
      foreach ($targetUserCampaigns as $targetUserCampaignCount => $targetUserCampaign) {

        // Skip any campaigns where the nid is invalid
        if (!isset($globalMergeVars[$targetUserCampaign->nid])) {
          continue;
        }

        foreach ($campaignDetails as $campaignDetailIndex => $campaignDetail) {

          // Check if the nid's match
          if (isset($targetUserCampaign->nid) &&
              isset($campaignDetail['drupal_nid']) &&
              $targetUserCampaign->nid == $campaignDetail['drupal_nid']) {

            if (isset($campaignDetail['is_staff_pick']) &&
                $campaignDetail['is_staff_pick'] == 'true') {
              $staffPicks[] = $targetUserCampaign;
            }
            else {
              $nonStaffPicks[] = $targetUserCampaign;
            }

            break;
          }

        }

      }

      // @todo: Add sort by high season

      // Sort staff picks by date
      usort($staffPicks, function($a, $b) {
        return $a->signup - $b->signup;
      });

      // Sort non-staff picks by date
      usort($nonStaffPicks, function($a, $b) {
        return $a->signup - $b->signup;
      });

      // Append the non-staff pick campaigns onto the end of the staff pick campaigns
      $targetUserCampaigns = $staffPicks + $nonStaffPicks;

      // Limit the number of campaigns in message to MAX_CAMPAIGNS
      if (count($targetUserCampaigns) > self::MAX_CAMPAIGNS) {
          $targetUserCampaigns = array_slice($targetUserCampaigns, 0, self::MAX_CAMPAIGNS);
      }

      $targetUsers[$targetUserIndex]['campaigns'] = $targetUserCampaigns;
    }

    return $targetUsers;
  }

  /**
   * Construct digest merge_var submissions based on the Mandrill API send-template
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
   * NOTE: The use of $globalMergeVars value in the $mergeVars submission for
   * each use is not ideal but necessary. Mandrill currently doesn't support
   * using global merge var values to customize individual merge var submissions.
   * A feature request has been made to "Chad Morris", developer at Mandrill
   * (2014-06-01).
   *
   * @param array $targetUsers
   *   The target user details.
   *
   * @param array $globalMergeVars
   *   Markup for each of the possible campaign entries in the digest message.
   *   Ideally the global_marge_var values could be referenced in the individual
   *   user merge_var entries but... this is currently not possible. The
   *   individual marge_var entries must have the complete markup for the
   *   campaigns of interest.
   *
   * @return array
   *   Details of all the active campaigns
   */
  private function composeMergeVars($targetUsers, $globalMergeVars) {

    $mergeVars = array();
    $campaignDividerMarkup = file_get_contents(__DIR__ . '/campaign-divider-markup.inc');

    foreach ($targetUsers as $targetUserIndex => $targetUser) {

      $campaignMergeVars = '';
      foreach ($targetUser['campaigns'] as $campaignCount => $campaign) {
        // Only add campaign details for items that content is available - not
        // expired or available in mb-campaign-api.
        if (isset($globalMergeVars[$campaign->nid])) {
          $campaignMergeVars .= $globalMergeVars[$campaign->nid]['content'];
          if (count($targetUser['campaigns']) - 1 > $campaignCount) {
            $campaignMergeVars .= $campaignDividerMarkup;
          }
        }
      }

      // Skip entries that result in in empty campaign listings.
      if ($campaignMergeVars != '') {
        $mergeVars[] = array(
          'rcpt' => $targetUser['email'],
          'vars' => array(
            0 => array(
              'name' => 'FNAME',
              'content' => $targetUser['fname'],
            ),
            1 => array(
              'name' => 'CAMPAIGNS',
              'content' => $campaignMergeVars,
            ),
            2 => array(
              'name' => 'SUBSCRIPTIONS_LINK',
              'content' => $this->$toolbox->subscriptionsLinkGenerator($targetUser['email']),
            ),
          )
        );
      }
      else {
        unset($targetUsers[$targetUserIndex]);
      }

    }

    echo '------- mbc-digest-email composeMergeVars: ' . count($mergeVars) . ' messages composed - ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
    return array($mergeVars, $targetUsers);
  }

  /**
   * Assemble digest message request for Mandrill submission based on Mandrill
   * API send-template specification.
   *
   * @param array $to
   *   List of recipients for the To <name> part of an email
   *
   * @param array $mergeVars
   *   Merge values keyed on the email addresses. Values include the user first
   *   name and the global merge var values for the campaigns the user is
   *   active in.
   *
   * @param array $globalMergeVars
   *   Merge values (campaign details) potentially common to all messages.
   */
  private function composeDigestSubmission($to, $mergeVars, $globalMergeVars) {

    /*
     * @todo: Add Google Analytics tracking
     *
     * "google_analytics_domains": [
     *   "example.com"
     * ],
     * "google_analytics_campaign": "message.from_email@example.com",
     * "metadata": {
     *   "website": "www.example.com"
     * },
     */
    $tags = array(
      0 => 'digest',
    );

    $subjects = array(
      'Your weekly DoSomething campaign digest',
      'Your weekly DoSomething.org campaign roundup!',
      'A weekly campaign digest just for you!',
      'Your weekly campaign digest: ' . date('F j'),
      date('F j') . ': Your weekly campaign digest!',
      'Tips for your DoSomething.org campaigns!',
      'Comin\' atcha: tips for your DoSomething.org campaign!',
      '*|FNAME|* - It\'s your ' . date('F j') . ' campaign digest',
      'Just for you: DoSomething.org campaign tips',
      'Your weekly campaign tips from DoSomething.org',
      date('F j') . ': campaign tips from DoSomething.org',
      'You signed up for campaigns. Here\'s how to rock them!',
      'Tips for you (and only you!)',
      'Ready for your weekly campaign tips?',
      'Your weekly campaign tips: comin\' atcha!',
      'Fresh out the oven (just for you!)',
    );
    // Sequenilly select an item from the list of subjects, a different one
    // every week and start from the top once the end of the list is reached
    $subjectCount = abs(date('W') - (round(date('W') / count($subjects)) * count($subjects)));

    $composedDigestSubmission = array(
      'subject' => $subjects[$subjectCount],
      'from_email' => 'help@dosomething.org',
      'from_name' => 'DoSomething.org',
      'to' => $to,
      'global_merge_vars' => $globalMergeVars,
      'merge_vars' => $mergeVars,
      'tags' => $tags,
    );

    return $composedDigestSubmission;
  }
  
  /**
   * Send digest message request to Mandrill.
   *
   * @param array $composedDigestSubmission
   *   Submission data formatted to the send-template API guidelines.
   */
  private function submitToMandrill($composedDigestSubmission) {

    // Send to Mandrill
    $mandrill = new Mandrill();
    $templateName = 'mb-digest-v0-5-1';

    // Must be included in submission but is kept blank as the template contents
    // are managed through the Mailchip/Mandril WYSIWYG interface.
    $templateContent = array(
      array(
          'name' => 'main',
          'content' => ''
      ),
    );

    // Send message
    $mandrillResults = $mandrill->messages->sendTemplate($templateName, $templateContent, $composedDigestSubmission);

    return $mandrillResults;
  }

}

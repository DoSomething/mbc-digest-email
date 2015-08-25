<?php
/**
 * MBC_DigestEmail_MandrillMessenger
 * 
 */

namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

/**
 * MBC_DigestEmail_MandrillMessenger class - 
 */
class MBC_DigestEmail_MandrillMessenger extends MBC_DigestEmail_BaseMessenger {

  // The maximum number of campaigns to include in a users digest message.
  const MAX_CAMPAIGNS = 5;

  /*
   *
   */
  private $users = [];

  /**
   *
   */
  private $campaigns = [];

  /**
   *
   */
  private $userIndex;

  /**
   *
   */
  private $campaignTempate;

  /**
   *
   */
  private $campaignTempateDivider;

  /**
   *
   */
  private $mandrill;

  /**
   *
   */
  private $globalMergeVars;

  /*
   *
   */
  public function __construct() {

    // Application configuration
    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolbox = $this->mbConfig->getProperty('mbToolbox');

    // Resources for building digest batch
    $this->userIndex = 0;
    $this->campaignTempate = parent::getTemplate('campaign-markup.inc');
    $this->campaignTempateDivider = parent::getTemplate('campaign-divider-markup.inc');
    $this->mandrill = new \Mandrill();
    $this->getGlobalMergeVars();
  }

  /**
   * addUser(): Coordinate the the construction of a user object resulting
   * in an addition to the users list to base future batch messaging.
   *
   * @param object $user
   */
  public function addUser($user) {

    // Add user object into list of users to send batch message to.
    $this->users[$user->email] = $user;
    $this->userIndex++;

    $this->generateUserMergeVars($user->email);
  }

  /**
   * addCampaign(): Add campaign object to class campaigns property indexed by the
   * campaign Drupal nid (node ID).
   *
   * @param MBC_DigestEmail_Campaign (object) $campaign
   *   Details of a campaign based on details gathered from the Drupal site based on a Drupal
   *   nid (node ID).
   */
  public function addCampaign( MBC_DigestEmail_Campaign $campaign) {

    if (!(isset($this->campaigns[$campaign->drupal_nid]))) {
        $this->campaigns[$campaign->drupal_nid] = $campaign;
        $this->generateCampaignMarkup($campaign);
    }
  }

  /**
   * generateCampaignMarkup(): Build campaign markup for Campaign object. To be used to
   * generate *|CAMPAIGNS|* user merge var.
   *
   * @param object $campaign
   */
  private function generateCampaignMarkup($campaign) {

    // Check for existing markup
    if (!(isset($this->campaigns[$campaign->drupal_nid]->markup))) {

      $campaignMarkup = $this->campaignTempate;

      $campaignMarkup = str_replace('*|CAMPAIGN_IMAGE_URL|*', $campaign->image_campaign_cover, $campaignMarkup);
      $campaignMarkup = str_replace('*|CAMPAIGN_TITLE|*', $campaign->title, $campaignMarkup);
      $campaignMarkup = str_replace('*|CAMPAIGN_LINK|*', $campaign->url, $campaignMarkup);
      $campaignMarkup = str_replace('*|CALL_TO_ACTION|*', $campaign->call_to_action, $campaignMarkup);

      if (isset($campaign->latest_news)) {
        $campaignMarkup = str_replace('*|TIP_TITLE|*',  'News from the team:', $campaignMarkup);
        $campaignMarkup = str_replace('*|DURING_TIP|*',  $campaign->latest_news, $campaignMarkup);
      }
      else {
        $campaignMarkup = str_replace('*|TIP_TITLE|*',  $campaign->during_tip_header, $campaignMarkup);
        $campaignMarkup = str_replace('*|DURING_TIP|*',  $campaign->during_tip_copy, $campaignMarkup);
      }

      $this->campaigns[$campaign->drupal_nid]->markup = $campaignMarkup;
    }
  }

  /**
   *
   */
  public function generateUserMergeVars($userEmail) {

    $firstName = $this->users[$userEmail]->first_name;
    $campaignsMarkup = $this->generateUserCampaignMarkup($userEmail);
    $unsubscribeLinkMarkup = $this->users[$userEmail]->subscriptions->url;

    $this->users[$userEmail]->merge_vars = [
      'FNAME' =>  $firstName,
      'CAMPAIGNS' => $campaignsMarkup,
      'UNSUBSCRIBE_LINK' => $unsubscribeLinkMarkup,
    ];
  }

  /**
   * generateUserCampaignMarkup(): Generate markup for campaign listing based on specific user
   * campaigns settings.
   *
   * @param string $userEmail
   *   The email address of the target user. Used to access users class property which is
   *   indexed by the users email string.
   *
   * @return string $markup
   *   HTML string of the user campaigns. Used as body of digest email message for
   *   specific user.
   */
  private function generateUserCampaignMarkup($userEmail) {

    $markup = '';
    $campaignCounter = 0;
    $totalCampaigns = count($this->users[$userEmail]->campaigns);

    foreach($this->users[$userEmail]->campaigns as $nid => $campaign) {
        $campaignCounter++;
        $markup .= $this->campaigns[$campaign->drupal_nid]->markup;

        // Add divider markup if more campaings are to be added
        if ($totalCampaigns - 1 > $campaignCounter) {
          $markup .= $this->campaignTempateDivider;
        }
    }

    return $markup;
  }

  /**
   *
   */
  private function getGlobalMergeVars() {

    $memberCount = $this->mbToolbox->getDSMemberCount();
    $currentYear = date('Y');

    $this->globalMergeVars = [
      'MEMBER_COUNT' => $memberCount,
      'CURRENT_YEAR' => $currentYear,
    ];
  }

  /**
   * getGlobalMergeVars(): Formatted global merge var values based on
   * Mandrill send-template API spec:
   * https://mandrillapp.com/api/docs/messages.JSON.html#method=send-template
   *
   * Global merge variables to use for all recipients. You can override these
   * per recipient.
   *
   * @return array $globalMergeVars
   *   A formatted array of global merge var values to be sent with
   *   digest batch.
   *
   */
  private function setGlobalMergeVars() {

    foreach($this->globalMergeVars as $name => $content) {
      $globalMergeVars[] = [
        'name' => $name,
        'content' => $content
      ];
    }

    return $globalMergeVars;
  }

  /**
   *
   */
  private function getUsersDigestSettings() {

    foreach($this->users as $user) {
      $userDigestSettings[] = [
        'to' => $this->setTo($user),
        'merger_vars' => $this->getUserMergeVars($user),
      ];
    }

    return $userDigestSettings;
  }

  /**
   *
   */
  private function getUserMergeVars() {

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
  private function setTo($user) {

    $to = [
      'email' => $user['email'],
      'name' => $user['fname'],
      'to' => 'to',
    ];
    return $to;
  }

  /*
   *
   */
  private function buildMergeUserVars($user) {

    $user->merge_vars['FNAME'] = ucfirst($user->first_name);
    $user->merge_vars['UNSUBSCRIBE_LINK'] = '';

    // Merge campaign template with campaign values
    $user->merge_vars['CAMPAIGNS'] = '';

    return $user;
  }

  /*
   * An array of string to tag the message with. Stats are accumulated using
   * tags, though we only store the first 100 we see, so this should not be
   * unique or change frequently. Tags should be 50 characters or less. Any
   * tags starting with an underscore are reserved for internal use and will
   * cause errors.
   *
   * @return array $tags
   *   A list of tags to be associated with the digest messages.
   */
  private function getDigestMessageTags() {

    $tags = array(
      0 => 'digest',
    );

    return $tags;
  }

  /*
   * getDigestMessageSubject(): Generate the message subject text.
   *
   * @return string $subject
   *   The dynamically generated message subject based on a list that
   *   will change weekly.
   */
  private function getDigestMessageSubject() {

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
    $subjectCount = (int) abs(date('W') - (round(date('W') / count($subjects)) * count($subjects)));

    return $subjects[$subjectCount];
  }

  /*
   * getDigestMessageFrom(): Generate the message from name and email adddress.
   *
   * @return array $from
   *   String values of the sender of the digest message.
   */
  private function getDigestMessageFrom() {

    $from = [
      'email' => 'noreply@dosomething.org',
      'name' => 'Ben, DoSomething.org'
    ];

    return $from;
  }

  /*
   *
   */
  private function composeDigestBatch() {

    // subject line
    $subject = $this->getDigestMessageSubject();

    // from_email
    // from_name
    $from = $this->getDigestMessageFrom();

    // Gather user settings in single request to ensure "to" and "marge_vars" are in sync
    $usersDigestSettings = $this->getUsersDigestSettings();
    // to
    $to = $usersDigestSettings['to'];

    // global merge vars
    $globalMergeVars = $this->getGlobalMergeVars();

    // User merge vars
    $userMergeVars = $usersDigestSettings['merge_vars'];

    // tags
    $tags = $this->getDigestMessageTags();

    $composedDigestSubmission = array(
      'subject' => $subjects[$subjectCount],
      'from_email' => $from['email'],
      'from_name' => $from['name'],
      'to' => $to,
      'global_merge_vars' => $globalMergeVars,
      'merge_vars' => $userMergeVars,
      'tags' => $tags,
    );

    return $composedDigestSubmission ;
  }

  /**
   *
   */
  public function sendDigestBatch() {

    $templateName = 'mb-digest-v0-5-1';
    // Must be included in submission but is kept blank as the template contents
    // are managed through the Mailchip/Mandril WYSIWYG interface.
    $templateContent = array(
      array(
          'name' => 'main',
          'content' => ''
      ),
    );

    $composedDigestBatch = $this->composeDigestBatch();

    $mandrillResults = $this->mandrill->messages->sendTemplate($templateName, $templateContent, $composedDigestBatch);
  }
}
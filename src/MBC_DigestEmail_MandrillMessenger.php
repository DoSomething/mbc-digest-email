<?php
/**
 * MBC_DigestEmail_MandrillMessenger
 * 
 */

namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use Mandrill\Mandrill;

/**
 * MBC_DigestEmail_MandrillMessenger class - 
 */
class MBC_DigestEmail_MandrillMessenger extends MBC_DigestEmail_BaseMessenger {

  /*
   *
   */
  private $users = [];

  /**
   *
   */
  private $campaigns;

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
  public function __construct($campaigns) {

    // Application configuration
    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolbox = $this->mbConfig->getProperty('mbToolbox');

    // Resources for building digest batch
    $this->campaigns = $campaigns;
    $this->userIndex = 0;
    $this->campaignTempate = parent::gatherTemplate('campaign-markup.inc');
    $this->campaignTempateDivider = parent::gatherTemplate('campaign-divider-markup.inc');
    $this->mandrill = new Mandrill();
    $this->memberCount = $this->mbToolbox->getDSMemberCount();
    $this->globalMergeVars = setGlobalMergeVars();
  }

  /**
   * addUser(): Coordinate the the construction of a user object resulting
   * in an addition to the users list for the batch message to be sent to.
   *
   * @param object $user
   */
  private function addUser($user) {

    $this->processUser($user);

    // Move constructed user object into list of users to send batch
    // message to.
    $this->users[] = $this->user;
    $this->userIndex++;
  }

  /*
   *
   */
  private function processUser($user) {

    // Apply digest campaign rules to user campaign signups
    $user->campaigns = $this->processUserCampaigns($user);

    // Ensure user campaigns have markup to go into their digest message
    foreach($user->campaigns as $nid => $campaign) {
      if (!(isset($campaign->markup))) {
        $user->campaigns[$nid]->markup = $this->generateCampaignMarkup($nid);
      }
    }
  }

  /*
   *
   */
  private function processUserCampaigns($user) {

  }

  /**
   * generateCampaignMarkup(): Build campaign and divider markup
   * CAMPAIGNS user merge var.
   *
   * @param object $user
   *   Details of the user to generate the digest message for.
   */
  private function generateCampaignMarkup($user) {

    foreach($user->campaigns as $campaign) {
      str_replace($this->campaignTempate, '');
    }
  }

  /**
   *
   */
  private function setGlobalMergeVars() {

    $memberCount = $this->memberCount;
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
  private function getGlobalMergeVars() {

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
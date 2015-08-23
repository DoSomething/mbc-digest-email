<?php
/**
 * MBC_DigestEmail_MandrillMessenger
 * 
 */

namespace DoSomething\MBC_DigestEmail;


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

  /*
   *
   */
  public function __construct($campaigns) {

    $this->campaigns = $campaigns;
    $this->userIndex = 0;
    $this->campaignTempate = parent::gatherTemplate('campaign-markup.inc');
    $this->campaignTempateDivider = parent::gatherTemplate('campaign-divider-markup.inc');
    $this->mandrill = new Mandrill();
  }

  /**
   * addUser(): Coordinate the the construction of a user object resulting
   * in an addition to the users list for the batch message to be sent to.
   *
   * @param object $user
   */
  private function addUser($user) {

    $this->processUser($user);

    $this->addToTo($user);
    $this->buildUserMergeVars($user);

    // Move constructed user object into list of users to send batch
    // message to.
    $this->users[] = $this->user;
    $this->userIndex++;
  }

  /*
   *
   */
  private function processUser($user) {

    $this->processUserCampaigns($user);
    $this->generateCampaignMarkup($user);
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
  private function addToTo($user) {

    $this->to[] = [
      'email' => $user['email'],
      'name' => $user['fname'],
      'to' => 'to',
    ];
  }

  /*
   *
   */
  private function buildMergeVars($user) {

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
   *
   */
  private function composeDigestBatch() {

    // tags
    $tags = $this->getDigestMessageTags();

    // subject line
    $subject = $this->getDigestMessageSubject();

    // from_email
    // from_name
    $from = $this->getDigestMessageFrom();

    $composedDigestSubmission = array(
      'subject' => $subjects[$subjectCount],
      'from_email' => 'noreply@dosomething.org',
      'from_name' => 'Ben, DoSomething.org',
      'to' => $to,
      'global_merge_vars' => $this->getGlobalMergeVars(),
      'merge_vars' => $this->getUserMergeVars,
      'tags' => $tags,
    );

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
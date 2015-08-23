<?php
/**
 * MBC_DigestEmail_Consumer
 * 
 * Consumer application to process messages in userDigestQueue. Queue
 * contents will be processed as a blocked application that responds to
 * queue contents immedatly. Each message will be processed to build user
 * objects that consist of a digest message propertry. The application will
 * dispatch groups of messages based on the composed user objects.
 */

namespace DoSomething\MBC_DigestEmail;

use DoSomething\StatHat\Client as StatHat;
use RabbitMq\ManagementApi\Client;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_BaseConsumer;

/**
 * MBC_DigestEmail_Consumer class - functionality related to the Message Broker
 * consumer mbc-digest-email application.
 *
 * - Coordinate building user object including digest message contant specific to the user.
 * - Trigger sending batches of digest messages.
 */
class MBC_DigestEmail_Consumer extends MB_Toolbox_BaseConsumer {

  /**
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
  private $mbcDEMandrillMessanger;

  /**
   *
   */
  public function __contruct() {

    // Future support of different Services other than Mandrill
    // could be toggled at this point with logic for user origin.
    // See mbc-registration-mobile for working example of toggling
    // Based on user origin.

    // Create new Message object for user.
    $this->mbcDEMessanger = new MBC_DigestEmail_MandrillMessenger();
  }

  /**
   * Coordinate processing of messages consumed fromn the target queue defined in the
   * application configuration.
   *
   * @param array $message
   *  The payload of the unserialized message being processed.
   */
  protected function consumeDigestUserQueue($message) {

    parent::consumeQueue();

    if (count($this->users) <= self::BATCH_SIZE) {

      if ($this->canProcess()) {

        $this->setter($message);

        // Build out user object and gather / trigger building campaign objects
        // based on user campaign activity
        $this->process();

      }

    }
    // Send batch of user digest messages
    else {

      // @todo: Support different services based on interface base class
      $status = $this->mbcDEMessanger->sendDigestBatch();
      $this->logStatus();

      unset($this->users);
    }

  }

  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $message
   *  The payload of the unserialized message being processed.
   */
  protected function setter($message) {

    // Create new user object
    $mbcDEUser = new MBC_DigestEmail_User($message['email']);

    // First name
    if (!(isset($userProperty['first_name']))) {
      $userProperty['first_name'] = '';
    }
    $mbcDEUser->setFirstName($userProperty['first_name']);

    // Language preference
    if (!(isset($userProperty['source']))) {
      $userProperty['source'] = 'US';
    }
    $mbcDEUser->setLanguage($userProperty['source']);

    // Drupal UID
    if (!(isset($userProperty['drupal_uid']))) {
      $userProperty['drupal_uid'] = '';
    }
    $mbcDEUser->setDrupalUID($userProperty['drupal_uid']);

    // List of campaign ids
    foreach($userProperty['campaigns'] as $campaign) {
      if (!(isset($this->campaigns[$campaign['nid']]))) {
        $mbcDECampaign = new MBC_DigestEmail_Campaign($campaign->nid);
        $this->campaigns[$campaign->nid] = $mbcDECampaign;
      }
      $mbcDEUser->addCampaign($this->campaigns[$campaign['nid']]);
    }

    // Set message ID for ack_back
    $mbcDEUser->setMessageID($message);

    // Add user object to users property of current instance of Consumer class.
    $this->users[] = $mbcDEUser;
  }

  /**
   * Evaluate message to determine if it can be processed based on formatting and
   * business rules.
   *
   * @param array $message
   *  The payload of the message being processed.
   */
  protected function canProcess() {

    if (!isset($this->message['email'])) {
      echo 'MBC_DigestEmail_Consumer->canProcess(): Message missing email.', PHP_EOL;
      return FALSE;
    }

    // Must have drupal_uid (for unsubscribe link)
    // Confirm there's no reportbacks
    // Confirm there's campaign signups to process, must be at least one

    return TRUE;
  }

  /**
   * Process message from consumed queue.
   */
  protected function process() {

    // Get last user to now process
    $user = array_pop($this->users);

    $this->mbcDEMessanger->addUser($user);

  }

  /**
   *
   */
  private function getCommonMergeVars($user) {

    $user->merge_vars['MEMBER_COUNT'] = '';
    $user->merge_vars['CURRENT_YEAR'] = '';

    return $user;
  }

}

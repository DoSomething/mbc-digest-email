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

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_BaseConsumer;
use \Exception;

/**
 * MBC_DigestEmail_Consumer class - functionality related to the Message Broker
 * consumer mbc-digest-email application.
 *
 * Coordinate building user and campaign objects. Constructed objects are sent to a Messenger object
 * to generate batches of digest messages to be sent through a Service object.
 */
class MBC_DigestEmail_Consumer extends MB_Toolbox_BaseConsumer {

  /**
   * A list of user objects.
   * @var array $users
   */
  private $users = [];

  /**
   * A list of user objects.
   * @var array $campaigns
   */
  private $campaigns = [];

  /**
   * A User object - each message from the consumed queue can result in a User object.
   * @var object $mbcDEUser
   */
  private $mbcDEUser;

  /**
   * A Messenger object. Handles combining User and Campain objects to compose a batch of digest
   * messages. The messages are sent in batches using a Service object (Mandrill).
   * @var object $mbcDEMessanger
   */
  private $mbcDEMessanger;

  /**
   * Collect errors reported when generating campaign object. The contents of this property will be
   * used to generate a report of campaigns missing content.
   * @var array $campaignErrors
   */
  private $campaignErrors;

  /**
   * __construct(): When a new Consumer class is created at the time of starting the mbc-digest-script
   * this method will ensure key variables are constructed.
   */
  public function __construct($batchSize) {

    $this->batchSize = $batchSize;

    parent::__construct();

    // Future support of different Services other than Mandrill could be toggled. Currently the Mandrill
    // service is hard coded.at There's not reason more than one service could be used at the same time
    // depending on the affiliates arrangements. Use of the differet Service classes could be toggled with
    // logic for user origin.

    // See mbc-registration-mobile for working example of toggling based on user origin.

    $this->mbConfig = MB_Configuration::getInstance();
    $this->mbcDEMessanger = $this->mbConfig->getProperty('mbcDEMessanger');
  }

  /**
   * Coordinate processing of messages consumed fromn the target queue defined in the
   * application configuration.
   *
   * @param array $message
   *  The payload of the unserialized message being processed.
   */
  public function consumeDigestUserQueue($message) {

    parent::consumeQueue($message);
    echo PHP_EOL . PHP_EOL;
    echo '** Processing: ' . $this->message['email'], PHP_EOL;

    // Process messages in batches for submission to the service. Once the number of
    // messages processed reached the BATCH_SIZE send messages.
    $waitingUserMessages = $this->waitingUserMessages();
    if ($waitingUserMessages < $this->batchSize) {

      if ($this->canProcess()) {

        $setterOK = $this->setter($this->message);

        // Build out user object and gather / trigger building campaign objects
        // based on user campaign activity
        if ($setterOK) {
          $this->process();
        }
      }

      $this->messageBroker->sendAck($this->message['payload']);
    }

    // Send batch of user digest messages OR
    // If the number of messages remaining to be processed is zero and there are user
    // objects waiting to be sent create a batch of messages from the remaining user objects.
    // Not clear on the ready vs unacked values as the docs suggest re-declaring
    $queueMessages = parent::queueStatus('digestUserQueue');
    $waitingUserMessages = $this->waitingUserMessages();
    if (($waitingUserMessages >= $this->batchSize) ||
        ($waitingUserMessages < $this->batchSize && $queueMessages['ready'] == 0)) {

      // @todo: Support different services based on interface base class
      $status = $this->mbcDEMessanger->sendDigestBatch();

      // @todo: Log digest message activity, include errors encountered generating campaign
      // objects: $this->campaignErrors
      //
      // $this->logStatus();

      unset($this->users);
    }
  }

/**
 * waitingUserMessages(): Report the number of users waiting to be processed. Used to determine if
 * the waiting users should be sent as a batch of messages. This is a reqwuirement at the end of a
 * digest run when the remaining users is less that a batch size. Without this test the last group of
 * users would not get processed.
 *
 * @return integer $userCount
 */
private function waitingUserMessages() {

  if (isset($this->users)) {
    $userCount = count($this->users);
  }
  else {
    $userCount = 0;
  }
  return $userCount;
}

  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $userProperty
   *  The indexed array of user properties based on the message sent to the digestUserQueue.
   */
  protected function setter($userProperty) {

    // Create new user object
    $this->mbcDEUser = new MBC_DigestEmail_User($userProperty['email']);

    // First name
    if (!(isset($userProperty['first_name']))) {
      $userProperty['first_name'] = '';
    }
    $this->mbcDEUser->setFirstName($userProperty['first_name']);

    // Language preference
    if (!(isset($userProperty['source']))) {
      $userProperty['source'] = 'US';
    }
    $this->mbcDEUser->setLanguage($userProperty['source']);

    // Drupal UID
    if (!(isset($userProperty['drupal_uid']))) {
      $userProperty['drupal_uid'] = '';
    }
    $this->mbcDEUser->setDrupalUID($userProperty['drupal_uid']);

    // List of campaign ids
    foreach($userProperty['campaigns'] as $campaign) {

      // Build campaign object if it does not already exist
      $mbcDECampaign = NULL;
      if (!(isset($this->campaigns[$campaign['nid']]))) {
        try {
          // Create Campaign object and add to Consumer property of all Campaigns to be processed
          // in batch being sent to the Messenger object.
          $mbcDECampaign = new MBC_DigestEmail_Campaign($campaign['nid']);
        }
        catch (Exception $e) {
          // @todo: Log/report missing campaign value.
          echo '- MBC_DigestEmail_Consumer->setter(): Error creating MBC_DigestEmail_Campaign object: ' . $e->getMessage(), PHP_EOL;
          $mbcDECampaign = [
            'nid' => $campaign['nid'],
            'creationError' => $e->getMessage(),
          ];
        }
        // Add campaign object to concerned properties and related objects.
        $this->campaigns[$campaign['nid']] = $mbcDECampaign;

        if (isset($mbcDECampaign->campaignErrors) && count($mbcDECampaign->campaignErrors) > 0) {
          $this->campaignErrors[$campaign['nid']] = $mbcDECampaign->campaignErrors;
        }
      }

      // Exclude campaings that are not functional Campaign objects.
      if (is_object($mbcDECampaign)) {
        $this->mbcDEMessanger->addCampaign($mbcDECampaign);
        $this->mbcDEUser->addCampaign($campaign['nid'], $campaign['signup']);
      }
    }

    // Keep track of the original message payload. This includes the message ID for ack_backs.
    $this->mbcDEUser->setOrginalPayload($userProperty['payload']);

    // Add user object to users property of current instance of Consumer class only in the case where the user
    // object has at least on campaign entry. It's possible to get to this point with no campaign entries due
    // to encountering Exceptions in the Campaign object creation process.
    if (count($this->mbcDEUser->campaigns) > 0) {
      $this->users[] = $this->mbcDEUser;
      return $this->mbcDEUser;
    }
    else {
      // Don't do any further processing on this message
      return FALSE;
    }
  }

  /**
   * Evaluate message to determine if it can be processed based on formatting and
   * business rules.
   *
   * @return boolean
   */
  protected function canProcess() {

    if (!isset($this->message['email'])) {
      echo 'MBC_DigestEmail_Consumer->canProcess(): Message missing email.', PHP_EOL;
      return FALSE;
    }

    // Must have drupal_uid (for unsubscribe link)
    if (!isset($this->message['drupal_uid'])) {
      echo 'MBC_DigestEmail_Consumer->canProcess(): Message missing uid (Drupal node ID).', PHP_EOL;
      return FALSE;
    }

    // Confirm there's no reportbacks. Should have been removed in the producer of the message.
    if (!(isset($this->message['campaigns']))) {
      foreach($this->message['campaigns'] as $campaign) {
        if (isset($campaign['reportback'])) {
          return FALSE;
        }
      }
    }

    // Confirm there's campaign signups to process, must be at least one
    if (!(isset($this->message['campaigns'])) && count($this->message['campaigns'] > 0)) {
      echo 'MBC_DigestEmail_Consumer->canProcess(): Missing active campaign signups to define digest message.', PHP_EOL;
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Process message from consumed queue. process() involves applying methods to existing objects as
   * a part of consuming a message in a queue.
   */
  protected function process() {

    $this->mbcDEUser->processUserCampaigns();
    $this->mbcDEUser->getSubsciptionsURL();
    $this->mbcDEMessanger->addUser($this->mbcDEUser);

    // Cleanup for processing of next message
    unset($this->mbcDEUser);
  }

}

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
use \Exception;

/**
 * MBC_DigestEmail_Consumer class - functionality related to the Message Broker
 * consumer mbc-digest-email application.
 *
 * - Coordinate building user object including digest message contant specific to the user.
 * - Trigger sending batches of digest messages.
 */
class MBC_DigestEmail_Consumer extends MB_Toolbox_BaseConsumer {

  // The number of messages to include in each batch digest submission to the service
  // that is used to send the messages.
  const BATCH_SIZE = 2;

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
  private $mbcDEUser;

  /**
   *
   */
  private $mbcDEMessanger;

  /**
   *
   */
  public function __construct() {

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
  public function consumeDigestUserQueue($message) {

    parent::consumeQueue($message);

    if (count($this->users) < self::BATCH_SIZE) {

      if ($this->canProcess()) {

        $setterOK = $this->setter($this->message);

        // Build out user object and gather / trigger building campaign objects
        // based on user campaign activity
        if ($setterOK) {
          $this->process();
        }
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
          echo 'MBC_DigestEmail_Consumer->setter(): Error creating  MBC_DigestEmail_Campaign object.' . $e->getMessage();
          $mbcDECampaign = [
            'nid' => $campaign['nid'],
            'creationError' => $e->getMessage(),
          ];
        }
        // Add campaign object to concerned properties and related objects.
        $this->campaigns[$campaign['nid']] = $mbcDECampaign;
      }

      // Exclude campaings that are not functional Campaign objects.
      if (is_object($mbcDECampaign)) {
        $this->mbcDEMessanger->addCampaign($mbcDECampaign);
        $this->mbcDEUser->addCampaign($campaign['nid'], $campaign['signup']);
      }
    }

    // Set message ID for ack_back
    $this->mbcDEUser->setMessageID($userProperty['payload']);

    // Add user object to users property of current instance of Consumer class only in the case where the user
    // object has at least on campaign entry. It's possible to get to this point with no campaign entries due
    // to encountering Exceptions.
    if (count($this->mbcDEUser->campaigns) > 0) {
      $this->users[] = $this->mbcDEUser;
      return $this->mbcDEUser;
    }
    else {
      return FALSE;
    }

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
    if (!isset($this->message['drupal_uid'])) {
      echo 'MBC_DigestEmail_Consumer->canProcess(): Message missing uid (Drupal node ID).', PHP_EOL;
      return FALSE;
    }

    // Confirm there's no reportbacks
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
   * Process message from consumed queue. process() involves apply methods to existing objects as
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

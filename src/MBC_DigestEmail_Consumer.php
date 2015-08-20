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

  function __construct() {

    // Do creation in config singleton

    // Create campaign object

    // Create Mandill Service object

  }

  /**
   * Coordinate processing of messages consumed fromn the target queue defined in the
   * application configuration.
   *
   * @param array $message
   *  The payload of the unserialized message being processed.
   */
  protected function consumeUserDigestQueue($message) {

    // Process message into basic format
    parent::consumeQueue();



    if (count($this->users) <= self::BATCH_SIZE) {

      if ($this->canProcess()) {

        $this->setter($message);

        // Build out user object and gather / trigger building campaign objects
        // based on user campaign activity
        $this->process();

      }

    }
    // Send batch of users digest message
    else {
      $mbcDigestEmailMandrillService->sendDigestBatch($this->users);
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
    $mbcDigestEmailUser = new MBC_DigestEmail_User();

    // Loop through message to set user values
    foreach ($message as $userProperty) {

    // Properties found for:
      // first_name, set to default if missing value
      // Country / affiliate (stub for future use)

      // List of campaign ids
      foreach($userProperty['campaigns'] as $campaign) {
        if ($this->canSetCampaign($this->mbcCampaigns->getCampaign($campaign))) {
          $mbcDigestEmailUser->campaigns[] = array(
            'nid' => $campaign['nid'],
            'markup' => $this->mbcCampaigns->getMarkup($campaign['nid']),
          );
        }
      }

      // Drupal_uid
      // Message ID for ack_back

      // unsubscribe link
      $mbcDigestEmailUser->unsubscribe_link = $this->MB_Toolbox->getUnsubscribeLink($userProperty['nid']);

    }

    // ... set user object
    $this->users[] = $mbcDigestEmailUser;

  }

  /**
   * Evaluate message to determine if it can be processed based on formatting and
   * business rules.
   *
   * @param array $message
   *  The payload of the message being processed.
   */
  protected function canProcess($message) {

    if (!isset($message['email'])) {
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

    // Get last user set
    $user = array_pop($this->users);

    // Processed OK, add user back to class property
    if ($processOK) {
      $this->users[] = $user;
    }
    else {
      // Log the user was not processed
    }

  }

}

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
  private $messageMarkup;

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
      $mbcDigestEmailMandrillService->sendDigestBatch($this->messageTemplate, $this->users);
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

    // Loop through message to set user values
    foreach ($message as $userProperty) {

      $mbcDEUser->setFirstName($userProperty['first_name']);
      $mbcDEUser->setLanguage($userProperty['source']);
      $mbcDEUser->setDrupalUID($userProperty['drupal_uid']);

      // List of campaign ids
      foreach($userProperty['campaigns'] as $campaign) {
        if (isset($this->campaigns[$campaign['nid']])) {
          $mbcDEUser->addCampaign($this->campaigns[$casmpaign['nid']]);
        }
        else {
          $mbcDECampaign = new MBC_DigestEmail_Campaign($campaign->nid);
        }
        $mbcDEUser->addCampaign($mbcDECampaign);
      }

      // Message ID for ack_back


    }

    // ... set user object
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

    // Get last user set
    $user = array_pop($this->users);

    $user->merge_vars['FNAME'] = '';
    $user->merge_vars['CAMPAIGNS'] = '';
    $user->merge_vars['UNSUBSCRIBE_LINK'] = '';

    $user = $this->getCommonMergeVars($user);

    // Processed OK, add user back to class property
    if ($processOK) {
      $this->users[] = $user;
    }
    else {
      // Log the user was not processed
    }

  }

  /**
   *
   */
  private function getCommonMergeVars($user) {

    $user->merge_vars['MEMBER_COUNT'] = '';

    return $user;
  }

}

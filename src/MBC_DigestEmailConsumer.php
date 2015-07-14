<?php
/**
 * MBC_DigestEmailConsumer
 * 
 * Consumer application to process messages in userDigestQueue. Queue
 * contents will be processed as a blocked application that responds to
 * queue contents immedatly. Each message will be processed to create
 * queue entries in:
 * 
 *  - digestCampaignRequestsQueue: Requests for campaign details needed
 *      to generate digest message contents.
 *  - digestUserRequestsQueue: Details of target users to generate digest
 *      messages.
 */

namespace DoSomething\MBC_DigestEmail;

use DoSomething\StatHat\Client as StatHat;
use RabbitMq\ManagementApi\Client;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_BaseConsumer;

/**
 * MBC_UserRegistration class - functionality related to the Message Broker
 * consumer mbc-registration-email.
 */
class MBC_DigestEmailConsumer extends MB_Toolbox_BaseConsumer {
  
  /**
   * Initial method triggered by blocked call in base mbc-??-??.php file. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param array $payload
   *   The contents of the queue entry
   */
  public function consumeQueue($message) {
    
    parent::consumeQueue($message);
    $this->setter($this->message);
    
    // Create instance of campaign class to create queue entry in digestCampaignRequestsQueue
    
    // Create instance of user class to create queue entry in digestUserRequestsQueue

    
  }
  
  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $message
   *  The payload of the unserialized message being processed.
   */
  protected function setter($message) {
    
    // Loop through message to set user values
    
    // Set campaign id
    
  }
  
  /**
   * Process message from consumed queue.
   */
  protected function process() {
  }
  
}

<?php
/**
 * mbc-user-digest.php
 *
 * A consumer to process entries in the userDigestQueue via the directUserDigest
 * exchange. The mbp-user-digest application produces the entries in the
 * queue.
 */

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/mb-secure-config.inc';
require_once __DIR__ . '/mb-config.inc';

require_once __DIR__ . '/MBC_UserDigest.class.inc';

// Settings
$credentials = array(
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
);
$config = array(
  'exchange' => array(
    'name' => getenv("MB_USER_DIGEST_EXCHANGE"),
    'type' => getenv("MB_USER_DIGEST_EXCHANGE_TYPE"),
    'passive' => getenv("MB_USER_DIGEST_EXCHANGE_PASSIVE"),
    'durable' => getenv("MB_USER_DIGEST_EXCHANGE_DURABLE"),
    'auto_delete' => getenv("MB_USER_DIGEST_EXCHANGE_AUTO_DELETE"),
  ),
  'queue' => array(
    array(
      'name' => getenv("MB_USER_DIGEST_QUEUE"),
      'passive' => getenv("MB_USER_DIGEST_QUEUE_PASSIVE"),
      'durable' => getenv("MB_USER_DIGEST_QUEUE_DURABLE"),
      'exclusive' => getenv("MB_USER_DIGEST_QUEUE_EXCLUSIVE"),
      'auto_delete' => getenv("MB_USER_DIGEST_QUEUE_AUTO_DELETE"),
      'bindingKey' => getenv("MB_USER_DIGEST_QUEUE_TOPIC_MB_TRANSACTIONAL_EXCHANGE_PATTERN"),
    ),
  ),
);
$settings = array(
  'stathat_ez_key' => getenv("STATHAT_EZKEY"),
  'ds_drupal_api_host' => getenv('DS_DRUPAL_API_HOST'),
  'ds_drupal_api_port' => getenv('DS_DRUPAL_API_PORT'),
);


echo '------- mbc-user-digest START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

// Kick off
$mbcDigestEmail = new MBC_UserDigest($credentials, $config, $settings);

// Process digest message requests by mbp-user-digest
$mbcDigestEmail->generateDigests();

echo '------- mbc-user-digest END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

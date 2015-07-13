<?php
/**
 * mbc-user-digest.php
 *
 * A consumer to process entries in the userDigestQueue via the directUserDigest
 * exchange. The mbp-user-digest application produces the entries in the
 * queue.
 */

date_default_timezone_set('America/New_York');

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';
use DoSomething\MB_Toolbox\MB_Configuration;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';
require_once __DIR__ . '/MBC_RabbitMQManagementAPI.class.inc';
require_once __DIR__ . '/MBC_UserDigest.class.inc';

// Settings
$credentials = array(
  'rabbit' => array(
    'host' =>  getenv("RABBITMQ_HOST"),
    'port' => getenv("RABBITMQ_PORT"),
    'username' => getenv("RABBITMQ_USERNAME"),
    'password' => getenv("RABBITMQ_PASSWORD"),
    'vhost' => getenv("RABBITMQ_VHOST"),
  ),
  'rabbitManagementAPI' => array(
    'host' => getenv("MB_RABBITMQ_MANAGEMENT_API_HOST"),
    'port' => getenv("MB_RABBITMQ_MANAGEMENT_API_PORT"),
    'username' => getenv("MB_RABBITMQ_MANAGEMENT_API_USERNAME"),
    'password' => getenv("MB_RABBITMQ_MANAGEMENT_API_PASSWORD"),
  ),
  'stathat' => array(
    'ez_key' => getenv("STATHAT_EZKEY"),
    'use_stathat_tracking' => getenv("USE_STAT_TRACKING"),
  ),
  'ds_drupal_api' => array(
    'host' => getenv('DS_DRUPAL_API_HOST'),
    'port' => getenv('DS_DRUPAL_API_PORT'),
    'username' => getenv("DS_DRUPAL_API_USERNAME"),
    'password' => getenv("DS_DRUPAL_API_PASSWORD"),
  ),
  'mb_user_api' => array(
    'host' =>  getenv("MB_USER_API_HOST"),
    'port' =>  getenv("MB_USER_API_PORT"),
  ),
  'subscriptions' => array(
    'url' => getenv("SUBSCRIPTIONS_URL"),
    'ip' => getenv("SUBSCRIPTIONS_IP"),
    'port' => getenv("SUBSCRIPTIONS_PORT"),
  )
);
$settings = array(
  'stathat_ez_key' => getenv("STATHAT_EZKEY"),
  'use_stathat_tracking' => getenv("USE_STAT_TRACKING"),
  'ds_drupal_api_host' => getenv('DS_DRUPAL_API_HOST'),
  'ds_drupal_api_port' => getenv('DS_DRUPAL_API_PORT'),
  'ds_drupal_api_username' => getenv("DS_DRUPAL_API_USERNAME"),
  'ds_drupal_api_password' => getenv("DS_DRUPAL_API_PASSWORD"),
  'ds_user_api_host' =>  getenv("DS_USER_API_HOST"),
  'ds_user_api_port' =>  getenv("DS_USER_API_PORT"),
  'subscriptions_url' => getenv("SUBSCRIPTIONS_URL"),
  'subscriptions_ip' => getenv("SUBSCRIPTIONS_IP"),
  'subscriptions_port' => getenv("SUBSCRIPTIONS_PORT"),
);

$mbcRabbitMQManagementAPI = new MBC_RabbitMQManagementAPI($credentials);
$exchangeName = 'directUserDigestExchange';
$targetQueue = $mbcRabbitMQManagementAPI->nextQueue($exchangeName);

$config = array();
$source = __DIR__ . '/messagebroker-config/mb_config.json';
$mb_config = new MB_Configuration($source, $settings);
$userDigestExchange = $mb_config->exchangeSettings('directUserDigestExchange');

$config = array(
  'exchange' => array(
    'name' => $userDigestExchange->name,
    'type' => $userDigestExchange->type,
    'passive' => $userDigestExchange->passive,
    'durable' => $userDigestExchange->durable,
    'auto_delete' => $userDigestExchange->auto_delete,
  ),
  'queue' => array(
    array(
      'name' => $targetQueue,
      'passive' => $userDigestExchange->queues->userDigestQueue->passive,
      'durable' =>  $userDigestExchange->queues->userDigestQueue->durable,
      'exclusive' =>  $userDigestExchange->queues->userDigestQueue->exclusive,
      'auto_delete' =>  $userDigestExchange->queues->userDigestQueue->auto_delete,
      'bindingKey' => $targetQueue,
    ),
  ),
  'consume' => array(
    'consumer_tag' => $targetQueue,
    'no_local' => $userDigestExchange->queues->userDigestQueue->consume->no_local,
    'no_ack' => $userDigestExchange->queues->userDigestQueue->consume->no_ack,
    'exclusive' => $userDigestExchange->queues->userDigestQueue->consume->exclusive,
    'nowait' => $userDigestExchange->queues->userDigestQueue->consume->nowait,
  ),
);

echo '------- mbc-user-digest START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

// Kick off
$mbcDigestEmail = new MBC_UserDigest($credentials, $config, $settings);

// Process digest message requests by mbp-user-digest
$mbcDigestEmail->generateDigests();

echo '------- mbc-user-digest END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

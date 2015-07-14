<?php
/**
 * Message Broker configuration settings for mbc-user-digest
 */

use DoSomething\MB_Toolbox\MB_Configuration;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';

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
    'stathat_disable_tracking' => getenv('DISABLE_STAT_TRACKING'),
  ),
  'ds_drupal_api' => array(
    'host' => getenv('DS_DRUPAL_API_HOST'),
    'port' => getenv('DS_DRUPAL_API_PORT'),
    'username' => getenv("DS_DRUPAL_API_USERNAME"),
    'password' => getenv("DS_DRUPAL_API_PASSWORD"),
  ),
);

$settings = array(
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

// Added to support o dosomething/mb-stat-tracker library use  in MB_Toolbox.
// Remove after library has been ipgraded to dosomething/stathat
$settings['stathat_ez_key'] = getenv("STATHAT_EZKEY");

$mbConfig = new MB_Configuration($settings, CONFIG_PATH . '/mb_config.json');
$config = $mbConfig->constructConfig('directUserDigestExchange', array('userDigestQueue'));

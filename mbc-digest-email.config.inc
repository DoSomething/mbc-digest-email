<?php
/**
 * Message Broker configuration settings for mbc-user-digest
 */

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_cURL;
use DoSomething\MB_Toolbox\MB_RabbitMQManagementAPI;
use DoSomething\MBC_DigestEmail\MBC_DigestEmail_MandrillMessenger;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';

$mbConfig = MB_Configuration::getInstance();

// Settings
$mbConfig->setProperty('rabbit_credentials', [
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
]);
$mbConfig->setProperty('rabbitapi_credentials', [
  'host' =>  getenv("MB_RABBITMQ_MANAGEMENT_API_HOST"),
  'port' => getenv("MB_RABBITMQ_MANAGEMENT_API_PORT"),
  'username' => getenv("MB_RABBITMQ_MANAGEMENT_API_USERNAME"),
  'password' => getenv("MB_RABBITMQ_MANAGEMENT_API_PASSWORD"),
]);
$mbConfig->setProperty('ds_drupal_api_config', [
  'host' => getenv("DS_DRUPAL_API_HOST"),
  'port' => getenv('DS_DRUPAL_API_PORT'),
  'username' => getenv("DS_DRUPAL_API_USERNAME"),
  'password' => getenv('DS_DRUPAL_API_PASSWORD'),
]);
$mbConfig->setProperty('mb_user_api_config', [
  'host' => getenv("MB_USER_API_HOST"),
  'port' => getenv('MB_USER_API_PORT')
]);

$subscriptionsSetting = $mbConfig->gatherSettings('subscriptions');
$mbConfig->setProperty('subscriptions_config', [
  'host' =>  $subscriptionsSetting->url,
  'ip' =>  $subscriptionsSetting->ip,
  'port' => $subscriptionsSetting->port,
]);

// Create connection to exchange and queue for processing of queue contents.
$mbRabbitConfig = $mbConfig->constructRabbitConfig('fanoutUserDigest', array('digestUserQueue'));
$mbConfig->setProperty('messageBroker_config', $mbRabbitConfig);

$rabbitCredentials = $mbConfig->getProperty('rabbit_credentials');
$messageBrokerConfig = $mbConfig->getProperty('messageBroker_config');
$mbConfig->setProperty('messageBroker', new MessageBroker($rabbitCredentials, $messageBrokerConfig));

$mbConfig->setProperty('mandrill', new Mandrill(
  getenv("MANDRILL_APIKEY")
));

$mbConfig->setProperty('statHat', new StatHat([
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => getenv("DISABLE_STAT_TRACKING")
]));

$mbConfig->setProperty('mbToolbox', new MB_Toolbox());
$mbConfig->setProperty('mbToolboxcURL', new MB_Toolbox_cURL());
$mbConfig->setProperty('mbcDEMessanger', new MBC_DigestEmail_MandrillMessenger());

$mbConfig->setProperty('mbRabbitMQManagementAPI', new MB_RabbitMQManagementAPI([
  'domain' => getenv("MB_RABBITMQ_MANAGEMENT_API_HOST"),
  'port' => getenv('MB_RABBITMQ_MANAGEMENT_API_PORT'),
  'vhost' => getenv('MB_RABBITMQ_MANAGEMENT_API_VHOST'),
  'password' => getenv('MB_RABBITMQ_MANAGEMENT_API_PASSWORD')
]));

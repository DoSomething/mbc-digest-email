<?php
/**
 * mbc-user-digest_campaigns.php
 *
 * A consumer to process entries in the digestCampaignRequeuestsQueue via
 * the directUserDigest exchange. The mbp-user-digest_campaigns application
 * processes the entries in the digestCampaignRequeuestsQueue to generate
 * message entries in the digestCampaignQueue of campaign markup items to
 * use for digest messages sent via Mandrill.
 */

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';
use DoSomething\MBC_DigestEmail\MBC_DigestEmailCampaignsConsumer;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

// Load configuration settings specific to this application
require_once __DIR__ . '/mbc-digest-email_campaigns.config.inc';

// Create objects for injection into MBC_ImageProcessor
$mb = new MessageBroker($credentials['rabbit'], $config);
$sh = new StatHat([
  'ez_key' => $credentials['stathat']['stathat_ez_key'],
  'debug' => $credentials['stathat']['stathat_disable_tracking']
]);
$tb = new MB_Toolbox($settings);


echo '------- mbc-user-digest START: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
$mb->consumeMessage(array(new MBC_DigestEmailCampaignsConsumer($mb, $sh, $tb, $settings), 'consumeUserDigestQueue'));
echo '------- mbc-user-digest END: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;

<?php
/**
 * mbc-user-digest.php
 *
 * A consumer to process entries in the userDigestQueue via the directUserDigest
 * exchange. The mbp-user-digest application produces the entries in the
 * queue.
 */

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';
use DoSomething\MBC_DigestEmail\MBC_DigestEmail_Consumer;

require_once __DIR__ . '/mbc-digest-email.config.inc';


echo '------- mbc-user-digest START: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;

// Kick off
$mb = $mbConfig->getProperty('messageBroker');
$mb->consumeMessage(array(new MBC_DigestEmail_Consumer(), 'consumeUserDigestQueue'));

echo '------- mbc-user-digest END: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;

<?php
/**
 * mbc-user-digest.php
 *
 * A consumer to process entries in the userDigestQueue via the directUserDigest
 * exchange. The mbp-user-digest application produces the entries in the
 * queue.
 */

use DoSomething\MBC_DigestEmail\MBC_DigestEmail_Consumer;

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');

// The number of digest messages (unique user email addresses) to compose before sending request to Mandrill
define('BATCH_SIZE', 50);

// Manage $_enviroment setting
if (isset($_GET['environment']) && allowedEnviroment($_GET['environment'])) {
    define('ENVIRONMENT', $_GET['environment']);
} elseif (isset($argv[1])&& allowedEnviroment($argv[1])) {
    define('ENVIRONMENT', $argv[1]);
} elseif ($env = loadConfig()) {
    echo 'environment.php exists, ENVIRONMENT defined as: ' . ENVIRONMENT, PHP_EOL;
} elseif (allowedEnviroment('local')) {
    define('ENVIRONMENT', 'local');
}

// The number of messages for the consumer to reserve with each callback
// Necessary for parallel processing when more than one consumer is running on the same queue.
define('QOS_SIZE', 1);

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration settings specific to this application
require_once __DIR__ . '/mbc-digest-email.config.inc';

// Kick off - block, wait for messages in queue
echo '------- mbc-user-digest START: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
$mb = $mbConfig->getProperty('messageBroker');
$mb->consumeMessage([new MBC_DigestEmail_Consumer(BATCH_SIZE), 'consumeDigestUserQueue'], QOS_SIZE);
echo '------- mbc-user-digest END: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;

/**
 * Test if enviroment setting is a supported value.
 *
 * @param string $setting Requested enviroment setting.
 *
 * @return boolean
 */
function allowedEnviroment($setting)
{

    $allowedEnviroments = [
        'local',
        'dev',
        'prod'
    ];

    if (in_array($setting, $allowedEnviroments)) {
        return true;
    }

    return false;
}

/**
 * Gather configuration settings for current application enviroment.
 *
 * @return boolean
 */
function loadConfig() {

    // Check that environment config file exists
    if (!file_exists (enviroment.php)) {
        return false;
    }
    include('./environment.php');

    return true;
}

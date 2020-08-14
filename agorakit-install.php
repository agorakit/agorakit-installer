<?php

require 'config.php';
require 'vendor/autoload.php';


/*** ensure we have client name */

if (!isset($argv[1])) {
    die('Please choose a client name as argument' . PHP_EOL);
}


function info($data)
{
    echo $data, PHP_EOL;
}

$client_name = $argv[1];

if (preg_match('/^[a-zA-Z]+[a-zA-Z0-9._]+$/', $client_name)) {
    // Valid
} else {
    die('Please choose a VALID client name as argument' . PHP_EOL);
}



info('Creating a site for ' . $client_name);

$url = $client_name . '.agorakit.org';

info('Will be available on ' . $url);


use GuzzleHttp\Client;

$client = new Client([
    // Base URI is used with relative requests
    'base_uri' => 'https://api.alwaysdata.com/',
    // You can set any number of default request options.
    'timeout'  => 10.0,
    'auth' => [$ad_api_key, ''],
    'exceptions' => false,
]);


/************ create website ****************/

$options = [
    'json' => [

        'name' => $url,
        'addresses' => ['https://' . $url],
        'type' => 'apache_standard',
        'path' => '/www/agorakit/' . $client_name . '/public',
        'ssl_force' => true,
    ]
];


$response = $client->post('v1/site/', $options);

info('Creating site');
info('Code received : ' . $response->getStatusCode());
info($response->getBody());

if ($response->getStatusCode() > 299) {
    die();
}

/************ create database ****************/

$options = [
    'json' => [
        'name' => 'agorakit_' . $client_name,
        'type' => 'MYSQL',
    ]
];

$response = $client->post('v1/database/', $options);

info('Creating DB');
info('Code received : ' . $response->getStatusCode());
info($response->getBody());

if ($response->getStatusCode() > 299) {
    die();
}

/************ create DB user */

function generatePassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $count = mb_strlen($chars);

    for ($i = 0, $result = ''; $i < $length; $i++) {
        $index = rand(0, $count - 1);
        $result .= mb_substr($chars, $index, 1);
    }

    return $result;
}


$db_password = generatePassword(16);
$db_username = substr('agorakit_' . $client_name, 0, 16);


$options = [
    'json' => [
        'name' => $db_username,
        'type' => 'MYSQL',
        'password' => $db_password,
        'permissions' => ['agorakit_' . $client_name => 'FULL']
    ]
];

$response = $client->post('v1/database/user/', $options);

info('Creating DB USER');
info('Code received : ' . $response->getStatusCode());
info($response->getBody());

if ($response->getStatusCode() > 299) {
    die();
}


/* create cron job  */

$options = [
    'json' => [
        'type' => 'TYPE_COMMAND',
        'date_type' => 'FREQUENCY',
        'argument' => 'php /home/agorakit/www/agorakit/' . $client_name . '/artisan schedule:run >/dev/null 2>&1',
        'ssh_user' => '171704',
        'frequency' => 5,
        'frequency_period' => 'minute'
    ]
];

$response = $client->post('v1/job/', $options);

info('Creating Cron job');
info('Code received : ' . $response->getStatusCode());
info($response->getBody());

if ($response->getStatusCode() > 299) {
    die();
}



/* create inbox */
$inbox_password = generatePassword(16);

$options = [
    'json' => [
        'domain' => '47759', // this is the id of agorakit.org
        'name' => $client_name,
        'password' => $inbox_password,
    ]
];

$response = $client->post('v1/mailbox/', $options);

info('Creating INBOX USER');
info('Code received : ' . $response->getStatusCode());
info($response->getBody());

if ($response->getStatusCode() > 299) {
    die();
}




/************** clone repository ***********/

info('Cloning site');

use phpseclib\Net\SSH2;

$ssh = new SSH2('ssh-agorakit.alwaysdata.net');
if (!$ssh->login($ssh_login, $ssh_password)) {
    exit('Could not logged in');
}

$ssh->setTimeout(240);

info ( $ssh->exec('cd www/agorakit; git clone https://github.com/agorakit/agorakit ' . $client_name));
info ( $ssh->exec('cd www/agorakit/' . $client_name . '; composer install --optimize-autoloader --no-dev'));


/************* Handle .env file */

info($ssh->exec('cd www/agorakit/' . $client_name . '; touch .env'));


function env($name, $value)
{
    global $ssh;
    global $client_name;
    $ssh->exec('cd www/agorakit/' . $client_name . '; echo  ' . $name . '=' . $value . ' >> .env');
}


env('APP_ENV', 'production');
env('APP_KEY', 'SomeRandomString');
env('APP_DEBUG', 'false');
env('APP_NAME', $client_name);
env('APP_LOG', 'daily');

env('APP_DEFAULT_LOCALE', 'en');
env('DB_HOST', 'mysql-agorakit.alwaysdata.net');
env('DB_DATABASE', 'agorakit_' . $client_name);
env('DB_USERNAME', $db_username);
env('DB_PASSWORD', $db_password);
env('CACHE_DRIVER', 'file');
env('SESSION_DRIVER', 'file');
env('QUEUE_DRIVER', 'sync');

env('MAIL_DRIVER', 'smtp');
env('MAIL_HOST', 'smtp-agorakit.alwaysdata.net');
env('MAIL_PORT', '25');

env('MAIL_USERNAME', 'null');
env('MAIL_PASSWORD', 'null');

env('MAIL_FROM', $client_name . '+admin-noreply@agorakit.org');
env('MAIL_FROM_NAME', $client_name);
env('MAIL_NOREPLY', 'noreply@agorakit.org');

env('INBOX_DRIVER', 'imap');
env('INBOX_HOST', 'imap-agorakit.alwaysdata.net');
env('INBOX_USERNAME', $client_name . '@agorakit.org');
env('INBOX_PASSWORD', $inbox_password);
env('INBOX_PREFFIX', $client_name . '+');
env('INBOX_SUFFIX', '@agorakit.org');


/********** generate key */

info($ssh->exec('cd www/agorakit/' . $client_name . '; php artisan key:generate --force'));




/**************** migrate ***************************/

info($ssh->exec('cd www/agorakit/' . $client_name . '; php artisan migrate --force'));


info($ssh->exec('cd www/agorakit/' . $client_name . '; php artisan storage:link'));



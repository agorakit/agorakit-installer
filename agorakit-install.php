<?php

require 'config.php';
require 'vendor/autoload.php';


if (!isset($argv[1])) {
    die ('Please choose a client name as argument' . PHP_EOL);
} 


$client_name = $argv[1];

echo 'Creating a site for ' . $client_name , PHP_EOL;

$url = $client_name . '.agorakit.org';

echo 'Will be available on ' . $url , PHP_EOL;


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
echo 'Creating site' , PHP_EOL;

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

if ($response->getStatusCode() == 200)
{
    echo 'Site create successfuly' , PHP_EOL;
}
else
{
    echo 'Error creating site' , PHP_EOL;
    echo $response->getBody();
}


/************ create database ****************/
echo 'Creating DB' , PHP_EOL;

$options = [
    'json' => [
        'name' => 'agorakit_' . $client_name,
        'type' => 'MYSQL',
    ]
];

$response = $client->post('v1/database/', $options);

if ($response->getStatusCode() == 200)
{
    echo 'database created successfuly' , PHP_EOL;
}
else
{
    echo 'Error creating DB' , PHP_EOL;
    echo $response->getBody();
}



/************** clone repository ***********/
/*
echo 'Cloning site' , PHP_EOL;

use phpseclib\Net\SSH2;

$ssh = new SSH2('ssh-agorakit.alwaysdata.net');
if (!$ssh->login($ssh_login, $ssh_password)) {
    exit('Could not logged in');
}

$ssh->setTimeout(240);

echo $ssh->exec('cd www/agorakit; git clone https://github.com/agorakit/agorakit ' . $client_name);
echo $ssh->exec('cd www/agorakit/' . $client_name . '; composer install');
echo $ssh->exec('cd www/agorakit/' . $client_name . '; cp .env.example .env');
echo $ssh->exec('cd www/agorakit/' . $client_name . '; php artisan key:generate');

*/

/**************** set correct config in .env ****************/

/*** add key ***/

/**************** migrate ***************************/



/**************** setup cron jobs ******************/

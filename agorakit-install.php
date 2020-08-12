<?php

require 'config.php';
require 'vendor/autoload.php';


if (!isset($argv[1])) {
    die ('Please choose a client name as argument' . PHP_EOL);
} 


function info($data)
{
    echo $data , PHP_EOL;
}

$client_name = $argv[1];

info ('Creating a site for ' . $client_name);

$url = $client_name . '.agorakit.org';

info ( 'Will be available on ' . $url );


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
info ( 'Creating site' );

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
    info ( 'Site create successfuly');
}
else
{
    info ( 'Error creating site');
    info ( $response->getBody());
    info ( $response->getStatusCode());
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
    info ('database created successfuly' );
}
else
{
    info ('Error creating DB');
    info ($response->getBody());
    info ($response->getStatusCode());
}



/************** clone repository ***********/

info ('Cloning site');

use phpseclib\Net\SSH2;

$ssh = new SSH2('ssh-agorakit.alwaysdata.net');
if (!$ssh->login($ssh_login, $ssh_password)) {
    exit('Could not logged in');
}

$ssh->setTimeout(240);

info ( $ssh->exec('cd www/agorakit; git clone https://github.com/agorakit/agorakit ' . $client_name));
info ( $ssh->exec('cd www/agorakit/' . $client_name . '; composer install'));
info ( $ssh->exec('cd www/agorakit/' . $client_name . '; cp .env.example .env'));
info ( $ssh->exec('cd www/agorakit/' . $client_name . '; php artisan key:generate --force'));



/**************** set correct config in .env ****************/

/*** add key ***/

/**************** migrate ***************************/



/**************** setup cron jobs ******************/

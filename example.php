<?php

require_once 'vendor/autoload.php';

use BrainWeb\HttpClient\Http\HttpClient;
use BrainWeb\HttpClient\Logger\FileLogger;
use BrainWeb\HttpClient\Retry\ExponentialBackoffStrategy;
use BrainWeb\HttpClient\Transport\CurlTransport;

// Create the client with included cURL transport
$client = new HttpClient(
    transport: new CurlTransport(timeout: 30),
    logger: new FileLogger('./var/log/http-client.log'),
    retryStrategy: new ExponentialBackoffStrategy(maxAttempts: 3),
    baseUrl: 'https://postman-echo.com',
    defaultHeaders: ['X-Api-Key' => 'your-api-key'],
);

// Send a POST request
$response = $client->post('/post', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

if ($response->isSuccessful()) {
    $data = $response->json();
    print_r($data);
}
<?php

require_once 'vendor/autoload.php';

use HttpClient\Http\HttpClient;
use HttpClient\Logger\FileLogger;
use HttpClient\Retry\ExponentialBackoffStrategy;
use HttpClient\Transport\CurlTransport;

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
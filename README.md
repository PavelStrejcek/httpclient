# HTTP Client

A simple, extensible HTTP client for PHP 8.4 with automatic retry logic and comprehensive error logging.

## Features

- **Automatic Retry**: Configurable retry strategy with exponential backoff
- **Non-linear Delays**: Exponential delay increase with optional jitter to prevent thundering herd
- **Comprehensive Logging**: All error states are logged with context
- **Clean Architecture**: Follows SOLID principles, easily extensible
- **PSR Compliant**: Follows PSR-4 autoloading and PSR-3 inspired logging
- **Fully Tested**: Comprehensive unit test coverage

## Requirements

- PHP 8.4+
- cURL extension
- Docker & Docker Compose (for development)

## Installation

### Using Docker (Recommended)

1. Clone the repository and navigate to the project directory:

```bash
cd httpclient
```

3. Copy .env.example:

```bash
cp .env.example .env
```

3. Start the Docker container:

```bash
docker compose up -d
```

4. Install dependencies:

```bash
docker compose exec php composer install
```

### Without Docker

If you have PHP 8.4+ installed locally:

```bash
composer install
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use HttpClient\Http\HttpClient;
use HttpClient\Logger\FileLogger;
use HttpClient\Retry\ExponentialBackoffStrategy;
use HttpClient\Transport\CurlTransport;

// Create the client with included cURL transport
$client = new HttpClient(
    transport: new CurlTransport(timeout: 30),
    logger: new FileLogger('/var/log/http-client.log'),
    retryStrategy: new ExponentialBackoffStrategy(maxAttempts: 3),
    baseUrl: 'https://api.example.com',
    defaultHeaders: ['X-Api-Key' => 'your-api-key'],
);

// Send a POST request
$response = $client->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

if ($response->isSuccessful()) {
    $data = $response->json();
    echo "User created with ID: " . $data['id'];
}
```

## Architecture

### Class Diagram

```
┌─────────────────────────────────┐
│          HttpClient             │
│─────────────────────────────────│
│ - transport: HttpTransportInterface
│ - logger: LoggerInterface       │
│ - retryStrategy: RetryStrategyInterface
│─────────────────────────────────│
│ + post(endpoint, body, headers) │
│ + send(request)                 │
└─────────────────────────────────┘
         │
         │ uses
         ▼
┌─────────────────────────────────┐
│    HttpTransportInterface       │
│         (Contract)              │
│─────────────────────────────────│
│ + send(HttpRequest): HttpResponse
└─────────────────────────────────┘
```

### Core Components

#### `HttpClient`

The main entry point. Orchestrates:
- Request building with base URL and default headers
- Retry logic delegation to the retry strategy
- Error logging at all stages
- Response handling

#### `HttpRequest`

Immutable value object representing an HTTP request:

```php
// Factory method for POST requests
$request = HttpRequest::post('https://api.example.com/users', [
    'name' => 'John',
]);

// Or construct directly
$request = new HttpRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    body: ['name' => 'John'],
    headers: ['Content-Type' => 'application/json'],
);

// Get JSON-encoded body
$json = $request->getJsonBody();

// Add headers (returns new instance)
$newRequest = $request->withHeaders(['Authorization' => 'Bearer token']);
```

#### `HttpResponse`

Immutable value object representing an HTTP response:

```php
$response = new HttpResponse(
    statusCode: 200,
    body: '{"id": 123}',
    headers: ['content-type' => 'application/json'],
);

// Check response status
$response->isSuccessful();  // 2xx status codes
$response->isClientError(); // 4xx status codes
$response->isServerError(); // 5xx status codes
$response->isRetryable();   // 408, 429, 500, 502, 503, 504

// Parse JSON body
$data = $response->json();

// Get header
$contentType = $response->getHeader('content-type');

// Get status reason
$reason = $response->getReasonPhrase(); // "OK", "Not Found", etc.
```

## Retry Strategy

### Exponential Backoff

The default retry strategy implements exponential backoff with optional jitter:

```
delay = min(baseDelay × (multiplier ^ attemptNumber) + jitter, maxDelay)
```

Example with defaults (base=100ms, multiplier=2):
- Attempt 1: ~200ms
- Attempt 2: ~400ms
- Attempt 3: ~800ms
- Attempt 4: ~1600ms

### Configuration

```php
use HttpClient\Retry\ExponentialBackoffStrategy;

// Custom configuration
$strategy = new ExponentialBackoffStrategy(
    maxAttempts: 5,           // Maximum retry attempts
    baseDelayMs: 100,         // Base delay in milliseconds
    multiplier: 2.0,          // Exponential multiplier
    maxDelayMs: 30000,        // Maximum delay cap (30 seconds)
    useJitter: true,          // Add random jitter to prevent thundering herd
    retryableStatusCodes: [408, 429, 500, 502, 503, 504],
);

// Preset strategies
$strategy = ExponentialBackoffStrategy::forRateLimitedApi(); // For APIs with rate limits
$strategy = ExponentialBackoffStrategy::aggressive();         // More attempts, shorter delays
$strategy = ExponentialBackoffStrategy::conservative();       // Fewer attempts, longer delays
```

### Retryable Status Codes

By default, the following HTTP status codes trigger a retry:

| Code | Description |
|------|-------------|
| 408  | Request Timeout |
| 429  | Too Many Requests |
| 500  | Internal Server Error |
| 502  | Bad Gateway |
| 503  | Service Unavailable |
| 504  | Gateway Timeout |

Non-retryable errors (4xx except 408, 429) will throw `HttpClientException` immediately.

## Logging

### File Logger

```php
use HttpClient\Logger\FileLogger;

$logger = new FileLogger(
    filePath: '/var/log/http-client.log',
    minLevel: 'warning', // Only log warning and above
);
```

Log format:
```
[2024-01-15 10:30:45.123456] [ERROR] HTTP request failed with non-retryable error {"method":"POST","url":"https://api.example.com/users","status_code":400}
```

### Null Logger

For testing or when logging is not needed:

```php
use HttpClient\Logger\NullLogger;

$logger = new NullLogger();
```

### Custom Logger

Implement `LoggerInterface`:

```php
use HttpClient\Contracts\LoggerInterface;

class DatabaseLogger implements LoggerInterface
{
    public function error(string $message, array $context = []): void
    {
        // Store in database
    }

    // ... other methods
}
```

## HTTP Transport

### Included: CurlTransport

The library includes a production-ready cURL transport:

```php
use HttpClient\Transport\CurlTransport;

$transport = new CurlTransport(
    timeout: 30,      // Connection timeout in seconds
    verifySSL: true,  // Verify SSL certificates (default: true)
);
```

Features:
- Supports POST, PUT, PATCH, DELETE methods
- Automatic JSON body encoding
- Response header parsing
- SSL certificate verification
- Proper error handling (timeout, DNS, connection failures)

### Custom Transport Implementation

You can implement `HttpTransportInterface` for other HTTP libraries (Guzzle, Symfony HttpClient, etc.):

```php
<?php

use HttpClient\Contracts\HttpTransportInterface;
use HttpClient\Exception\HttpTransportException;
use HttpClient\Http\HttpRequest;
use HttpClient\Http\HttpResponse;

final class GuzzleTransport implements HttpTransportInterface
{
    public function __construct(
        private readonly \GuzzleHttp\Client $client,
    ) {}

    public function send(HttpRequest $request): HttpResponse
    {
        try {
            $response = $this->client->request($request->method, $request->url, [
                'json' => $request->body,
                'headers' => $request->headers,
                'http_errors' => false,
            ]);

            return new HttpResponse(
                $response->getStatusCode(),
                (string) $response->getBody(),
            );
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw HttpTransportException::connectionFailed($request->url, $e);
        }
    }
}
```

## Exception Handling

### Exception Hierarchy

```
HttpClientException (base)
├── MaxRetriesExceededException
└── HttpTransportException
```

### Handling Errors

```php
use HttpClient\Exception\HttpClientException;
use HttpClient\Exception\MaxRetriesExceededException;

try {
    $response = $client->post('/users', ['name' => 'John']);
} catch (MaxRetriesExceededException $e) {
    // All retry attempts failed
    echo "Failed after {$e->attempts} attempts\n";
    echo "Last status: {$e->response?->statusCode}\n";
} catch (HttpClientException $e) {
    // Non-retryable error (4xx)
    echo "Request failed: {$e->getMessage()}\n";
    echo "Status code: {$e->getCode()}\n";

    // Access the response
    if ($e->response !== null) {
        $errorBody = $e->response->json();
    }
}
```

## Testing

The project includes two test suites:

| Suite | Tests | Description |
|-------|-------|-------------|
| **Unit** | 81 | Fast tests using mocked dependencies |
| **Functional** | 16 | Integration tests against httpbin.org |

### Running Tests with Docker

```bash
# Run unit tests only (fast, no network required)
docker compose exec php ./vendor/bin/phpunit --testsuite Unit

# Run functional tests (requires internet, calls httpbin.org)
docker compose exec php ./vendor/bin/phpunit --group functional

# Run all tests
docker compose exec php ./vendor/bin/phpunit --testsuite Unit
docker compose exec php ./vendor/bin/phpunit --group functional
```

### Running Tests without Docker

```bash
# Run unit tests
./vendor/bin/phpunit --testsuite Unit

# Run functional tests
./vendor/bin/phpunit --group functional

# Using composer script
composer test
```

### Test Configuration

- **Unit tests** are excluded from functional group by default in `phpunit.xml`
- **Functional tests** are marked with `#[Group('functional')]` attribute
- Running `./vendor/bin/phpunit` without arguments runs only unit tests

### Using Mock Transport in Tests

```php
use HttpClient\Tests\Mock\MockTransport;
use HttpClient\Tests\Mock\SpyLogger;

$transport = new MockTransport();
$logger = new SpyLogger();

// Queue responses
$transport
    ->queueResponse(new HttpResponse(500)) // First attempt fails
    ->queueResponse(new HttpResponse(200, '{"ok":true}')); // Second succeeds

$client = new HttpClient($transport, $logger);
$response = $client->post('/test', []);

// Assert
$this->assertSame(2, $transport->getRequestCount());
$this->assertTrue($logger->hasLogContaining('HTTP request failed', 'warning'));
```

## Complete Usage Example

```php
<?php

require_once 'vendor/autoload.php';

use HttpClient\Http\HttpClient;
use HttpClient\Logger\FileLogger;
use HttpClient\Retry\ExponentialBackoffStrategy;
use HttpClient\Transport\CurlTransport;
use HttpClient\Exception\MaxRetriesExceededException;
use HttpClient\Exception\HttpClientException;

// 1. Create transport
$transport = new CurlTransport(timeout: 30);

// 2. Configure logging
$logger = new FileLogger(
    filePath: __DIR__ . '/logs/http-client.log',
    minLevel: 'debug',
);

// 3. Configure retry strategy
$retryStrategy = new ExponentialBackoffStrategy(
    maxAttempts: 3,
    baseDelayMs: 200,
    multiplier: 2.0,
    useJitter: true,
);

// 4. Create the client
$client = new HttpClient(
    transport: $transport,
    logger: $logger,
    retryStrategy: $retryStrategy,
    baseUrl: 'https://api.example.com/v1',
    defaultHeaders: [
        'Accept' => 'application/json',
        'X-Api-Version' => '2024-01',
    ],
);

// 5. Make requests
try {
    $response = $client->post('/orders', [
        'product_id' => 'SKU-12345',
        'quantity' => 2,
        'customer' => [
            'email' => 'customer@example.com',
            'name' => 'Jane Doe',
        ],
    ]);

    if ($response->isSuccessful()) {
        $order = $response->json();
        echo "Order created: {$order['id']}\n";
    }
} catch (MaxRetriesExceededException $e) {
    error_log("Order creation failed after {$e->attempts} attempts");
    // Handle failure - maybe queue for later retry
} catch (HttpClientException $e) {
    error_log("Order creation rejected: {$e->getMessage()}");
    // Handle validation error
}
```

## Project Structure

```
├── src/
│   ├── Contracts/
│   │   ├── HttpTransportInterface.php    # Transport abstraction
│   │   ├── LoggerInterface.php           # PSR-3 inspired logger
│   │   └── RetryStrategyInterface.php    # Retry strategy contract
│   ├── Exception/
│   │   ├── HttpClientException.php       # Base exception
│   │   ├── HttpTransportException.php    # Transport layer errors
│   │   └── MaxRetriesExceededException.php
│   ├── Http/
│   │   ├── HttpClient.php                # Main client with retry logic
│   │   ├── HttpRequest.php               # Immutable request object
│   │   └── HttpResponse.php              # Immutable response object
│   ├── Logger/
│   │   ├── FileLogger.php                # File-based logger
│   │   └── NullLogger.php                # Null object pattern
│   ├── Retry/
│   │   └── ExponentialBackoffStrategy.php
│   └── Transport/
│       └── CurlTransport.php             # cURL implementation
├── tests/
│   ├── Functional/
│   │   └── HttpClientFunctionalTest.php  # Integration tests (httpbin.org)
│   ├── Mock/
│   │   ├── MockTransport.php             # Mock for unit tests
│   │   └── SpyLogger.php                 # Spy logger for assertions
│   ├── ExponentialBackoffStrategyTest.php
│   ├── HttpClientTest.php
│   ├── HttpRequestTest.php
│   └── HttpResponseTest.php
├── docker/
│   └── php/
│       └── Dockerfile                    # PHP 8.4 container
├── docker-compose.yml
├── composer.json
├── phpunit.xml
└── README.md
```

## License

MIT License

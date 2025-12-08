<?php

declare(strict_types=1);

namespace HttpClient\Tests;

use HttpClient\Http\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpResponse::class)]
final class HttpResponseTest extends TestCase
{
    #[Test]
    public function it_stores_status_code_and_body(): void
    {
        $response = new HttpResponse(200, '{"data":"test"}');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('{"data":"test"}', $response->body);
    }

    #[Test]
    public function it_stores_headers(): void
    {
        $response = new HttpResponse(200, '', ['content-type' => 'application/json']);

        $this->assertSame('application/json', $response->getHeader('content-type'));
    }

    #[Test]
    public function it_returns_null_for_missing_header(): void
    {
        $response = new HttpResponse(200);

        $this->assertNull($response->getHeader('x-missing'));
    }

    #[Test]
    #[DataProvider('successfulStatusCodesProvider')]
    public function it_identifies_successful_responses(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        $this->assertTrue($response->isSuccessful());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function successfulStatusCodesProvider(): array
    {
        return [
            'OK' => [200],
            'Created' => [201],
            'Accepted' => [202],
            'No Content' => [204],
        ];
    }

    #[Test]
    #[DataProvider('clientErrorStatusCodesProvider')]
    public function it_identifies_client_errors(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        $this->assertTrue($response->isClientError());
        $this->assertFalse($response->isSuccessful());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function clientErrorStatusCodesProvider(): array
    {
        return [
            'Bad Request' => [400],
            'Unauthorized' => [401],
            'Forbidden' => [403],
            'Not Found' => [404],
            'Too Many Requests' => [429],
        ];
    }

    #[Test]
    #[DataProvider('serverErrorStatusCodesProvider')]
    public function it_identifies_server_errors(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        $this->assertTrue($response->isServerError());
        $this->assertFalse($response->isSuccessful());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function serverErrorStatusCodesProvider(): array
    {
        return [
            'Internal Server Error' => [500],
            'Bad Gateway' => [502],
            'Service Unavailable' => [503],
            'Gateway Timeout' => [504],
        ];
    }

    #[Test]
    #[DataProvider('retryableStatusCodesProvider')]
    public function it_identifies_retryable_responses(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        $this->assertTrue($response->isRetryable());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function retryableStatusCodesProvider(): array
    {
        return [
            'Request Timeout' => [408],
            'Too Many Requests' => [429],
            'Internal Server Error' => [500],
            'Bad Gateway' => [502],
            'Service Unavailable' => [503],
            'Gateway Timeout' => [504],
        ];
    }

    #[Test]
    public function it_decodes_json_body(): void
    {
        $response = new HttpResponse(200, '{"name":"John","age":30}');

        $data = $response->json();

        $this->assertSame(['name' => 'John', 'age' => 30], $data);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $response = new HttpResponse(200, 'not json');

        $this->expectException(\JsonException::class);

        $response->json();
    }

    #[Test]
    public function it_returns_reason_phrase_for_known_status_codes(): void
    {
        $this->assertSame('OK', (new HttpResponse(200))->getReasonPhrase());
        $this->assertSame('Created', (new HttpResponse(201))->getReasonPhrase());
        $this->assertSame('Bad Request', (new HttpResponse(400))->getReasonPhrase());
        $this->assertSame('Not Found', (new HttpResponse(404))->getReasonPhrase());
        $this->assertSame('Internal Server Error', (new HttpResponse(500))->getReasonPhrase());
    }

    #[Test]
    public function it_returns_unknown_for_undefined_status_codes(): void
    {
        $response = new HttpResponse(418); // I'm a teapot

        $this->assertSame('Unknown', $response->getReasonPhrase());
    }
}

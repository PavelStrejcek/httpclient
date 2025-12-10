<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Tests;

use BrainWeb\HttpClient\Http\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HttpResponse::class)]
final class HttpResponseTest extends TestCase
{
    #[Test]
    public function itStoresStatusCodeAndBody(): void
    {
        $response = new HttpResponse(200, '{"data":"test"}');

        self::assertSame(200, $response->statusCode);
        self::assertSame('{"data":"test"}', $response->body);
    }

    #[Test]
    public function itStoresHeaders(): void
    {
        $response = new HttpResponse(200, '', ['content-type' => 'application/json']);

        self::assertSame('application/json', $response->getHeader('content-type'));
    }

    #[Test]
    public function itReturnsNullForMissingHeader(): void
    {
        $response = new HttpResponse(200);

        self::assertNull($response->getHeader('x-missing'));
    }

    #[Test]
    #[DataProvider('provideItIdentifiesSuccessfulResponsesCases')]
    public function itIdentifiesSuccessfulResponses(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        self::assertTrue($response->isSuccessful());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideItIdentifiesSuccessfulResponsesCases(): iterable
    {
        return [
            'OK' => [200],
            'Created' => [201],
            'Accepted' => [202],
            'No Content' => [204],
        ];
    }

    #[Test]
    #[DataProvider('provideItIdentifiesClientErrorsCases')]
    public function itIdentifiesClientErrors(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        self::assertTrue($response->isClientError());
        self::assertFalse($response->isSuccessful());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideItIdentifiesClientErrorsCases(): iterable
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
    #[DataProvider('provideItIdentifiesServerErrorsCases')]
    public function itIdentifiesServerErrors(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        self::assertTrue($response->isServerError());
        self::assertFalse($response->isSuccessful());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideItIdentifiesServerErrorsCases(): iterable
    {
        return [
            'Internal Server Error' => [500],
            'Bad Gateway' => [502],
            'Service Unavailable' => [503],
            'Gateway Timeout' => [504],
        ];
    }

    #[Test]
    #[DataProvider('provideItIdentifiesRetryableResponsesCases')]
    public function itIdentifiesRetryableResponses(int $statusCode): void
    {
        $response = new HttpResponse($statusCode);

        self::assertTrue($response->isRetryable());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideItIdentifiesRetryableResponsesCases(): iterable
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
    public function itDecodesJsonBody(): void
    {
        $response = new HttpResponse(200, '{"name":"John","age":30}');

        $data = $response->json();

        self::assertSame(['name' => 'John', 'age' => 30], $data);
    }

    #[Test]
    public function itThrowsOnInvalidJson(): void
    {
        $response = new HttpResponse(200, 'not json');

        $this->expectException(\JsonException::class);

        $response->json();
    }

    #[Test]
    public function itReturnsReasonPhraseForKnownStatusCodes(): void
    {
        self::assertSame('OK', (new HttpResponse(200))->getReasonPhrase());
        self::assertSame('Created', (new HttpResponse(201))->getReasonPhrase());
        self::assertSame('Bad Request', (new HttpResponse(400))->getReasonPhrase());
        self::assertSame('Not Found', (new HttpResponse(404))->getReasonPhrase());
        self::assertSame('Internal Server Error', (new HttpResponse(500))->getReasonPhrase());
    }

    #[Test]
    public function itReturnsUnknownForUndefinedStatusCodes(): void
    {
        $response = new HttpResponse(418); // I'm a teapot

        self::assertSame('Unknown', $response->getReasonPhrase());
    }
}

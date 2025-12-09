<?php

declare(strict_types=1);

namespace HttpClient\Tests;

use HttpClient\Http\HttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HttpRequest::class)]
final class HttpRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestWithAllProperties(): void
    {
        $request = new HttpRequest(
            url: 'https://api.example.com/users',
            method: 'POST',
            body: ['name' => 'John'],
            headers: ['Authorization' => 'Bearer token'],
        );

        self::assertSame('https://api.example.com/users', $request->url);
        self::assertSame('POST', $request->method);
        self::assertSame(['name' => 'John'], $request->body);
        self::assertSame(['Authorization' => 'Bearer token'], $request->headers);
    }

    #[Test]
    public function itDefaultsToPostMethod(): void
    {
        $request = new HttpRequest('https://api.example.com/users');

        self::assertSame('POST', $request->method);
    }

    #[Test]
    public function itCreatesPostRequestWithFactoryMethod(): void
    {
        $request = HttpRequest::post(
            'https://api.example.com/users',
            ['name' => 'John'],
            ['X-Custom' => 'value'],
        );

        self::assertSame('https://api.example.com/users', $request->url);
        self::assertSame('POST', $request->method);
        self::assertSame(['name' => 'John'], $request->body);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame('value', $request->headers['X-Custom']);
    }

    #[Test]
    public function itEncodesBodyAsJson(): void
    {
        $request = new HttpRequest(
            url: 'https://api.example.com',
            body: ['name' => 'John', 'tags' => ['a', 'b']],
        );

        self::assertSame('{"name":"John","tags":["a","b"]}', $request->getJsonBody());
    }

    #[Test]
    public function itThrowsOnNonEncodableBody(): void
    {
        $resource = fopen('php://memory', 'r');
        $request = new HttpRequest(
            url: 'https://api.example.com',
            body: ['invalid' => $resource],
        );

        $this->expectException(\JsonException::class);

        try {
            $request->getJsonBody();
        } finally {
            fclose($resource);
        }
    }

    #[Test]
    public function itCreatesNewInstanceWithAdditionalHeaders(): void
    {
        $original = new HttpRequest(
            url: 'https://api.example.com',
            headers: ['X-Original' => 'value'],
        );

        $modified = $original->withHeaders(['X-New' => 'new-value']);

        // Original unchanged
        self::assertArrayNotHasKey('X-New', $original->headers);

        // New instance has both headers
        self::assertSame('value', $modified->headers['X-Original']);
        self::assertSame('new-value', $modified->headers['X-New']);
    }

    #[Test]
    public function itOverwritesHeadersWithSameKey(): void
    {
        $original = new HttpRequest(
            url: 'https://api.example.com',
            headers: ['X-Header' => 'original'],
        );

        $modified = $original->withHeaders(['X-Header' => 'overwritten']);

        self::assertSame('overwritten', $modified->headers['X-Header']);
    }

    #[Test]
    public function itIsImmutable(): void
    {
        $request = new HttpRequest(
            url: 'https://api.example.com',
            body: ['key' => 'value'],
        );

        // Readonly class ensures immutability
        self::assertSame('https://api.example.com', $request->url);
    }

    #[Test]
    public function itCreatesGetRequestWithFactoryMethod(): void
    {
        $request = HttpRequest::get(
            'https://api.example.com/users',
            ['Authorization' => 'Bearer token'],
        );

        self::assertSame('https://api.example.com/users', $request->url);
        self::assertSame('GET', $request->method);
        self::assertSame([], $request->body);
        self::assertSame('Bearer token', $request->headers['Authorization']);
    }

    #[Test]
    public function itCreatesPutRequestWithFactoryMethod(): void
    {
        $request = HttpRequest::put(
            'https://api.example.com/users/1',
            ['name' => 'Jane'],
            ['X-Custom' => 'value'],
        );

        self::assertSame('https://api.example.com/users/1', $request->url);
        self::assertSame('PUT', $request->method);
        self::assertSame(['name' => 'Jane'], $request->body);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame('value', $request->headers['X-Custom']);
    }

    #[Test]
    public function itCreatesPatchRequestWithFactoryMethod(): void
    {
        $request = HttpRequest::patch(
            'https://api.example.com/users/1',
            ['name' => 'Jane'],
            ['X-Custom' => 'value'],
        );

        self::assertSame('https://api.example.com/users/1', $request->url);
        self::assertSame('PATCH', $request->method);
        self::assertSame(['name' => 'Jane'], $request->body);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame('value', $request->headers['X-Custom']);
    }

    #[Test]
    public function itCreatesDeleteRequestWithFactoryMethod(): void
    {
        $request = HttpRequest::delete(
            'https://api.example.com/users/1',
            ['Authorization' => 'Bearer token'],
        );

        self::assertSame('https://api.example.com/users/1', $request->url);
        self::assertSame('DELETE', $request->method);
        self::assertSame([], $request->body);
        self::assertSame('Bearer token', $request->headers['Authorization']);
    }
}

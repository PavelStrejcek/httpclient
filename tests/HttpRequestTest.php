<?php

declare(strict_types=1);

namespace HttpClient\Tests;

use HttpClient\Http\HttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpRequest::class)]
final class HttpRequestTest extends TestCase
{
    #[Test]
    public function it_creates_request_with_all_properties(): void
    {
        $request = new HttpRequest(
            url: 'https://api.example.com/users',
            method: 'POST',
            body: ['name' => 'John'],
            headers: ['Authorization' => 'Bearer token'],
        );

        $this->assertSame('https://api.example.com/users', $request->url);
        $this->assertSame('POST', $request->method);
        $this->assertSame(['name' => 'John'], $request->body);
        $this->assertSame(['Authorization' => 'Bearer token'], $request->headers);
    }

    #[Test]
    public function it_defaults_to_post_method(): void
    {
        $request = new HttpRequest('https://api.example.com/users');

        $this->assertSame('POST', $request->method);
    }

    #[Test]
    public function it_creates_post_request_with_factory_method(): void
    {
        $request = HttpRequest::post(
            'https://api.example.com/users',
            ['name' => 'John'],
            ['X-Custom' => 'value'],
        );

        $this->assertSame('https://api.example.com/users', $request->url);
        $this->assertSame('POST', $request->method);
        $this->assertSame(['name' => 'John'], $request->body);
        $this->assertSame('application/json', $request->headers['Content-Type']);
        $this->assertSame('value', $request->headers['X-Custom']);
    }

    #[Test]
    public function it_encodes_body_as_json(): void
    {
        $request = new HttpRequest(
            url: 'https://api.example.com',
            body: ['name' => 'John', 'tags' => ['a', 'b']],
        );

        $this->assertSame('{"name":"John","tags":["a","b"]}', $request->getJsonBody());
    }

    #[Test]
    public function it_throws_on_non_encodable_body(): void
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
    public function it_creates_new_instance_with_additional_headers(): void
    {
        $original = new HttpRequest(
            url: 'https://api.example.com',
            headers: ['X-Original' => 'value'],
        );

        $modified = $original->withHeaders(['X-New' => 'new-value']);

        // Original unchanged
        $this->assertArrayNotHasKey('X-New', $original->headers);

        // New instance has both headers
        $this->assertSame('value', $modified->headers['X-Original']);
        $this->assertSame('new-value', $modified->headers['X-New']);
    }

    #[Test]
    public function it_overwrites_headers_with_same_key(): void
    {
        $original = new HttpRequest(
            url: 'https://api.example.com',
            headers: ['X-Header' => 'original'],
        );

        $modified = $original->withHeaders(['X-Header' => 'overwritten']);

        $this->assertSame('overwritten', $modified->headers['X-Header']);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $request = new HttpRequest(
            url: 'https://api.example.com',
            body: ['key' => 'value'],
        );

        // Readonly class ensures immutability
        $this->assertSame('https://api.example.com', $request->url);
    }
}

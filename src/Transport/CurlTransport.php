<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Transport;

use BrainWeb\HttpClient\Contracts\HttpTransportInterface;
use BrainWeb\HttpClient\Exception\HttpTransportException;
use BrainWeb\HttpClient\Http\HttpRequest;
use BrainWeb\HttpClient\Http\HttpResponse;

/**
 * cURL-based HTTP transport implementation.
 *
 * Provides actual HTTP communication using PHP's cURL extension.
 */
final readonly class CurlTransport implements HttpTransportInterface
{
    /**
     * @param int  $timeout   Connection timeout in seconds
     * @param bool $verifySSL Whether to verify SSL certificates
     */
    public function __construct(
        private int $timeout = 30,
        private bool $verifySSL = true,
    ) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $ch = curl_init();

        if (false === $ch) {
            throw new HttpTransportException('Failed to initialize cURL');
        }

        $this->configureRequest($ch, $request);

        $response = curl_exec($ch);

        if (false === $response) {
            $this->handleCurlError($ch, $request->url);
        }

        // @var string $response
        return $this->parseResponse($ch, $response);
    }

    /**
     * Configure cURL options for the request.
     */
    private function configureRequest(\CurlHandle $ch, HttpRequest $request): void
    {
        curl_setopt_array($ch, [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);

        $this->configureMethod($ch, $request);
        $this->configureHeaders($ch, $request);
    }

    /**
     * Configure HTTP method and body.
     */
    private function configureMethod(\CurlHandle $ch, HttpRequest $request): void
    {
        match ($request->method) {
            'POST' => $this->configurePost($ch, $request),
            'PUT' => $this->configurePut($ch, $request),
            'PATCH' => $this->configurePatch($ch, $request),
            'DELETE' => curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default => null, // GET is default
        };
    }

    private function configurePost(\CurlHandle $ch, HttpRequest $request): void
    {
        curl_setopt($ch, CURLOPT_POST, true);

        if ([] !== $request->body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getJsonBody());
        }
    }

    private function configurePut(\CurlHandle $ch, HttpRequest $request): void
    {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

        if ([] !== $request->body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getJsonBody());
        }
    }

    private function configurePatch(\CurlHandle $ch, HttpRequest $request): void
    {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');

        if ([] !== $request->body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getJsonBody());
        }
    }

    /**
     * Configure request headers.
     */
    private function configureHeaders(\CurlHandle $ch, HttpRequest $request): void
    {
        if ([] === $request->headers) {
            return;
        }

        $headers = array_map(
            static fn (string $key, string $value): string => "{$key}: {$value}",
            array_keys($request->headers),
            array_values($request->headers),
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Handle cURL errors.
     *
     * @throws HttpTransportException
     */
    private function handleCurlError(\CurlHandle $ch, string $url): never
    {
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        throw match ($errno) {
            CURLE_OPERATION_TIMEDOUT => HttpTransportException::timeout($url, $this->timeout),
            CURLE_COULDNT_RESOLVE_HOST => HttpTransportException::dnsResolutionFailed(parse_url($url, PHP_URL_HOST) ?? $url),
            default => HttpTransportException::connectionFailed($url, new \RuntimeException($error, $errno)),
        };
    }

    /**
     * Parse cURL response into HttpResponse object.
     */
    private function parseResponse(\CurlHandle $ch, string $response): HttpResponse
    {
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = $this->parseHeaders($headerString);

        return new HttpResponse($statusCode, $body, $headers);
    }

    /**
     * Parse raw headers string into associative array.
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }
}

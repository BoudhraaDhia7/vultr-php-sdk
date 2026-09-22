<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Http;

use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\Exception\ApiException;
use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\VultrClient;
use Closure;

/**
 * Turns SDK-level calls into HTTP requests and HTTP responses into arrays,
 * applying authentication, retries and error mapping along the way.
 *
 * Resource classes talk to this; they never touch an HttpClientInterface directly.
 */
final class Transport
{
    /** @var Closure(float): void */
    private readonly Closure $sleeper;

    /**
     * @param Closure(float): void|null $sleeper overridable so tests do not wait for backoff delays
     */
    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $httpClient,
        ?Closure $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Send a request and return the decoded JSON body.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @throws ApiException       on any non-2xx response
     * @throws TransportException when no response could be obtained
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        return $this->send($method, $path, $query, $body)->json();
    }

    /**
     * Send a request and return the raw response, for callers that need headers
     * or a status code rather than a decoded body.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $request = $this->buildRequest($method, $path, $query, $body);

        $attempt = 0;

        while (true) {
            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if ($this->shouldRetryTransportFailure($request, $attempt)) {
                    $this->sleep($attempt, null);
                    ++$attempt;

                    continue;
                }

                throw $exception;
            }

            if ($response->isSuccessful()) {
                return $response;
            }

            if ($this->shouldRetryResponse($request, $response, $attempt)) {
                $this->sleep($attempt, $response);
                ++$attempt;

                continue;
            }

            throw ApiException::from($request->withRedactedCredentials(), $response);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function buildRequest(string $method, string $path, array $query = [], ?array $body = null): Request
    {
        $method = strtoupper($method);
        $uri = $this->config->baseUri().ltrim($path, '/');
        $queryString = self::buildQuery($query);

        if ('' !== $queryString) {
            $uri .= (str_contains($uri, '?') ? '&' : '?').$queryString;
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->config->apiKey(),
            'Accept' => 'application/json',
            'User-Agent' => self::userAgent(),
        ] + $this->config->defaultHeaders();

        $encodedBody = null;

        if (null !== $body) {
            $encodedBody = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $headers['Content-Type'] = 'application/json';
        }

        return new Request($method, $uri, $headers, $encodedBody);
    }

    /**
     * Scalars are cast the way the API expects: booleans as "true"/"false",
     * and list values repeated as key=a&key=b.
     *
     * @param array<string, mixed> $query
     */
    private static function buildQuery(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            if (null === $value) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_bool($item)) {
                    $item = $item ? 'true' : 'false';
                }

                $pairs[] = rawurlencode((string) $key).'='.rawurlencode((string) $item);
            }
        }

        return implode('&', $pairs);
    }

    private function shouldRetryTransportFailure(Request $request, int $attempt): bool
    {
        return $attempt < $this->config->maxRetries() && $request->isIdempotent();
    }

    private function shouldRetryResponse(Request $request, Response $response, int $attempt): bool
    {
        if ($attempt >= $this->config->maxRetries()) {
            return false;
        }

        // A rate-limited request was rejected before it was processed, so it is
        // safe to replay whatever its method is. A 5xx may have been applied,
        // so only idempotent methods are replayed.
        if (429 === $response->statusCode()) {
            return true;
        }

        return $response->statusCode() >= 500 && $request->isIdempotent();
    }

    private function sleep(int $attempt, ?Response $response): void
    {
        $retryAfter = $this->retryAfterSeconds($response);

        if (null !== $retryAfter) {
            ($this->sleeper)(min($retryAfter, $this->config->maxRetryDelay()));

            return;
        }

        $delay = min(
            $this->config->retryBaseDelay() * (2 ** $attempt),
            $this->config->maxRetryDelay(),
        );

        // Full jitter, so concurrent workers do not retry in lockstep.
        ($this->sleeper)($delay * (random_int(0, 1000) / 1000));
    }

    private function retryAfterSeconds(?Response $response): ?float
    {
        $header = $response?->header('Retry-After');

        if (null === $header) {
            return null;
        }

        $header = trim($header);

        if (ctype_digit($header)) {
            return (float) $header;
        }

        $timestamp = strtotime($header);

        return false === $timestamp ? null : (float) max(0, $timestamp - time());
    }

    private static function userAgent(): string
    {
        return sprintf('vultr-php-sdk/%s (PHP %s)', VultrClient::VERSION, PHP_VERSION);
    }
}

<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr;

use BoudhraaDhia7\Vultr\Exception\ConfigurationException;

/**
 * Immutable client configuration.
 *
 * Every mutator returns a new instance, so a configured object can be shared
 * safely between clients and long-running workers.
 */
final class Config
{
    public const DEFAULT_BASE_URI = 'https://api.vultr.com/';

    /**
     * @param string                $apiKey          personal access token from the Vultr customer portal
     * @param string                $baseUri         API root, overridable for testing against a mock server
     * @param float                 $connectTimeout  seconds to wait for a connection
     * @param float                 $timeout         seconds to wait for a complete response
     * @param int                   $maxRetries      retry attempts for rate limits, 5xx responses and transport errors
     * @param float                 $retryBaseDelay  first backoff delay in seconds; doubles on each attempt
     * @param float                 $maxRetryDelay   ceiling for a single backoff delay, in seconds
     * @param array<string, string> $defaultHeaders  headers added to every request
     * @param string|null           $caBundle        path to a CA bundle, for hosts with an incomplete trust store
     */
    private function __construct(
        private readonly string $apiKey,
        private readonly string $baseUri = self::DEFAULT_BASE_URI,
        private readonly float $connectTimeout = 10.0,
        private readonly float $timeout = 30.0,
        private readonly int $maxRetries = 2,
        private readonly float $retryBaseDelay = 0.5,
        private readonly float $maxRetryDelay = 8.0,
        private readonly array $defaultHeaders = [],
        private readonly ?string $caBundle = null,
    ) {
    }

    public static function create(string $apiKey, string $baseUri = self::DEFAULT_BASE_URI): self
    {
        $apiKey = trim($apiKey);

        if ('' === $apiKey) {
            throw new ConfigurationException(
                'A Vultr API key is required. Create one in the customer portal under Account > API.',
            );
        }

        return new self($apiKey, self::normaliseBaseUri($baseUri));
    }

    /**
     * Read the API key from an environment variable.
     *
     * Keeps credentials out of source control and out of stack traces.
     */
    public static function fromEnvironment(string $variable = 'VULTR_API_KEY'): self
    {
        $value = getenv($variable);

        if (!is_string($value) || '' === trim($value)) {
            $value = $_ENV[$variable] ?? $_SERVER[$variable] ?? null;
        }

        if (!is_string($value) || '' === trim($value)) {
            throw new ConfigurationException(sprintf(
                'Environment variable %s is not set or is empty.',
                $variable,
            ));
        }

        return self::create($value);
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function baseUri(): string
    {
        return $this->baseUri;
    }

    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function retryBaseDelay(): float
    {
        return $this->retryBaseDelay;
    }

    public function maxRetryDelay(): float
    {
        return $this->maxRetryDelay;
    }

    /**
     * @return array<string, string>
     */
    public function defaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    public function caBundle(): ?string
    {
        return $this->caBundle;
    }

    public function withBaseUri(string $baseUri): self
    {
        return $this->with(baseUri: self::normaliseBaseUri($baseUri));
    }

    public function withTimeout(float $timeout): self
    {
        if ($timeout <= 0) {
            throw new ConfigurationException('The request timeout must be greater than zero.');
        }

        return $this->with(timeout: $timeout);
    }

    public function withConnectTimeout(float $connectTimeout): self
    {
        if ($connectTimeout <= 0) {
            throw new ConfigurationException('The connect timeout must be greater than zero.');
        }

        return $this->with(connectTimeout: $connectTimeout);
    }

    /**
     * Set how many times a retryable failure is retried. Zero disables retries.
     */
    public function withMaxRetries(int $maxRetries): self
    {
        if ($maxRetries < 0) {
            throw new ConfigurationException('The retry count cannot be negative.');
        }

        return $this->with(maxRetries: $maxRetries);
    }

    public function withRetryDelays(float $baseDelay, float $maxDelay): self
    {
        if ($baseDelay < 0 || $maxDelay < 0) {
            throw new ConfigurationException('Retry delays cannot be negative.');
        }

        if ($maxDelay < $baseDelay) {
            throw new ConfigurationException('The maximum retry delay cannot be lower than the base delay.');
        }

        return $this->with(retryBaseDelay: $baseDelay, maxRetryDelay: $maxDelay);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withDefaultHeaders(array $headers): self
    {
        return $this->with(defaultHeaders: $headers);
    }

    /**
     * Point TLS verification at a specific CA bundle file or directory.
     *
     * This is the supported way to deal with a host whose trust store cannot
     * verify Vultr's certificate. Verification itself is never disabled.
     */
    public function withCaBundle(string $path): self
    {
        if (!file_exists($path)) {
            throw new ConfigurationException(sprintf('CA bundle "%s" does not exist.', $path));
        }

        return $this->with(caBundle: $path);
    }

    /**
     * @param array<string, string>|null $defaultHeaders
     */
    private function with(
        ?string $baseUri = null,
        ?float $connectTimeout = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
        ?float $retryBaseDelay = null,
        ?float $maxRetryDelay = null,
        ?array $defaultHeaders = null,
        ?string $caBundle = null,
    ): self {
        return new self(
            $this->apiKey,
            $baseUri ?? $this->baseUri,
            $connectTimeout ?? $this->connectTimeout,
            $timeout ?? $this->timeout,
            $maxRetries ?? $this->maxRetries,
            $retryBaseDelay ?? $this->retryBaseDelay,
            $maxRetryDelay ?? $this->maxRetryDelay,
            $defaultHeaders ?? $this->defaultHeaders,
            $caBundle ?? $this->caBundle,
        );
    }

    private static function normaliseBaseUri(string $baseUri): string
    {
        $baseUri = trim($baseUri);

        if ('' === $baseUri) {
            throw new ConfigurationException('The base URI cannot be empty.');
        }

        $scheme = parse_url($baseUri, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException(sprintf(
                'The base URI must be an absolute http or https URL, got "%s".',
                $baseUri,
            ));
        }

        return rtrim($baseUri, '/').'/';
    }
}

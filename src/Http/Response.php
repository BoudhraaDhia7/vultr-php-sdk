<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Http;

use BoudhraaDhia7\Vultr\Exception\TransportException;
use JsonException;

/**
 * An immutable HTTP response.
 */
final class Response
{
    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * @param array<string, string> $headers header names are matched case-insensitively
     */
    public function __construct(
        private readonly int $statusCode,
        array $headers = [],
        private readonly string $body = '',
    ) {
        $normalised = [];

        foreach ($headers as $name => $value) {
            $normalised[strtolower((string) $name)] = $value;
        }

        $this->headers = $normalised;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Decode the body as a JSON object.
     *
     * @throws TransportException when the body is not a JSON object
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ('' === trim($this->body)) {
            return [];
        }

        $decoded = $this->jsonOrNull();

        if (null === $decoded) {
            throw new TransportException(sprintf(
                'Expected a JSON object from the Vultr API but received: %s',
                substr($this->body, 0, 200),
            ));
        }

        return $decoded;
    }

    /**
     * Decode the body as a JSON object, or return null when that is not possible.
     *
     * @return array<string, mixed>|null
     */
    public function jsonOrNull(): ?array
    {
        if ('' === trim($this->body)) {
            return null;
        }

        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Remaining requests in the current rate-limit window, when advertised.
     */
    public function rateLimitRemaining(): ?int
    {
        $value = $this->header('X-RateLimit-Remaining');

        return null !== $value && ctype_digit(trim($value)) ? (int) trim($value) : null;
    }
}

<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Http;

/**
 * An immutable description of one outbound HTTP request.
 *
 * The URI is already absolute and the query string is already appended by the
 * time an HttpClientInterface implementation receives it.
 */
final class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $headers = [],
        private readonly ?string $body = null,
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
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
        foreach ($this->headers as $key => $value) {
            if (0 === strcasecmp($key, $name)) {
                return $value;
            }
        }

        return null;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * Headers as a list of "Name: value" strings, which is what cURL expects.
     *
     * @return list<string>
     */
    public function headerLines(): array
    {
        $lines = [];

        foreach ($this->headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        return $lines;
    }

    /**
     * A copy of this request with the Authorization header masked, safe to put
     * in logs and exception messages.
     */
    public function withRedactedCredentials(): self
    {
        $headers = [];

        foreach ($this->headers as $name => $value) {
            $headers[$name] = 0 === strcasecmp($name, 'Authorization') ? 'Bearer [redacted]' : $value;
        }

        return new self($this->method, $this->uri, $headers, $this->body);
    }

    /**
     * True for methods that may safely be replayed after a transport failure
     * or a 5xx response without changing the outcome.
     */
    public function isIdempotent(): bool
    {
        return in_array(strtoupper($this->method), ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'], true);
    }
}

<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

use BoudhraaDhia7\Vultr\Http\Request;
use BoudhraaDhia7\Vultr\Http\Response;

/**
 * Thrown when Vultr answered with a non-success HTTP status.
 *
 * Use {@see ApiException::from()} rather than constructing subclasses by hand;
 * it selects the most specific subclass for the status code.
 */
class ApiException extends VultrException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly Request $request,
        private readonly Response $response,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Build the most specific exception available for the given response.
     */
    public static function from(Request $request, Response $response): self
    {
        $status = $response->statusCode();
        $message = self::extractMessage($response, $status);

        return match (true) {
            401 === $status => new AuthenticationException($message, $status, $request, $response),
            403 === $status => new AuthorizationException($message, $status, $request, $response),
            404 === $status => new ResourceNotFoundException($message, $status, $request, $response),
            429 === $status => new RateLimitException($message, $status, $request, $response),
            400 === $status, 422 === $status => new ValidationException($message, $status, $request, $response),
            $status >= 500 => new ServerException($message, $status, $request, $response),
            default => new self($message, $status, $request, $response),
        };
    }

    /**
     * The HTTP status code Vultr replied with.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The request that produced this error, with the API key already redacted.
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * The full response, including headers and the raw body.
     */
    public function response(): Response
    {
        return $this->response;
    }

    /**
     * The decoded error payload, or an empty array when the body was not JSON.
     *
     * @return array<string, mixed>
     */
    public function errorBody(): array
    {
        return $this->response->jsonOrNull() ?? [];
    }

    private static function extractMessage(Response $response, int $status): string
    {
        $body = $response->jsonOrNull();

        if (is_array($body) && isset($body['error']) && is_string($body['error']) && '' !== $body['error']) {
            return $body['error'];
        }

        $raw = trim($response->body());

        if ('' !== $raw) {
            return sprintf('Vultr API returned HTTP %d: %s', $status, self::truncate($raw));
        }

        return sprintf('Vultr API returned HTTP %d with an empty body.', $status);
    }

    private static function truncate(string $value, int $limit = 500): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit).'...';
    }
}

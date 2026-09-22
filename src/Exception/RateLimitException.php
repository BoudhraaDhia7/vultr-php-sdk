<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

/**
 * HTTP 429. The account exceeded Vultr's request rate limit.
 *
 * The client retries rate-limited requests automatically up to
 * Config::maxRetries() times before this is thrown.
 */
final class RateLimitException extends ApiException
{
    /**
     * Seconds the caller should wait before retrying, as advertised by the
     * Retry-After header. Null when the header was absent or unparseable.
     */
    public function retryAfter(): ?int
    {
        $header = $this->response()->header('Retry-After');

        if (null === $header) {
            return null;
        }

        if (ctype_digit(trim($header))) {
            return (int) trim($header);
        }

        $timestamp = strtotime($header);

        if (false === $timestamp) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}

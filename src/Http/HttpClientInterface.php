<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Http;

use BoudhraaDhia7\Vultr\Exception\TransportException;

/**
 * The transport seam of the SDK.
 *
 * Implement this to route requests through your own HTTP stack. Two
 * implementations ship with the library: {@see CurlHttpClient} (the default,
 * no dependencies) and {@see Psr18HttpClient} (wraps any PSR-18 client).
 */
interface HttpClientInterface
{
    /**
     * Send a request and return the response.
     *
     * Implementations must return the response for every HTTP status,
     * including 4xx and 5xx; mapping statuses to exceptions is the caller's job.
     *
     * @throws TransportException when no response could be obtained at all
     */
    public function send(Request $request): Response;
}

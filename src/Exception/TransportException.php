<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

/**
 * Thrown when the request never produced an HTTP response: DNS failure,
 * refused connection, TLS handshake failure, timeout, and so on.
 *
 * No response is available, so there is no status code to inspect.
 */
final class TransportException extends VultrException
{
}

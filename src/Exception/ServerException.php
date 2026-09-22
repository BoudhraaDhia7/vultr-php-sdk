<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

/**
 * HTTP 5xx. Vultr failed to process an otherwise valid request.
 *
 * Idempotent requests are retried automatically before this is thrown;
 * see Config::maxRetries().
 */
final class ServerException extends ApiException
{
}

<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

/**
 * HTTP 401. The API key is missing, malformed, or has been revoked.
 */
final class AuthenticationException extends ApiException
{
}

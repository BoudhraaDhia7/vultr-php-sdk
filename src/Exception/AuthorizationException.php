<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

/**
 * HTTP 403. The API key is valid but lacks the ACL for this operation,
 * or the caller's IP is outside the key's allowed subnets.
 */
final class AuthorizationException extends ApiException
{
}

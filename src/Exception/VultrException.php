<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

use RuntimeException;

/**
 * Base class for every exception thrown by this library.
 */
class VultrException extends RuntimeException implements VultrExceptionInterface
{
}

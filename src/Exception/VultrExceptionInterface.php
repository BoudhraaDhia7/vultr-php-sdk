<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

use Throwable;

/**
 * Implemented by every exception this library throws.
 *
 * Catch this interface to handle all SDK failures without tying application
 * code to a concrete exception class.
 */
interface VultrExceptionInterface extends Throwable
{
}

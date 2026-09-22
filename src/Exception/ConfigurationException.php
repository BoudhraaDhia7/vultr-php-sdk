<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Exception;

/**
 * Thrown when the client is configured with values it cannot work with,
 * for example an empty API key or a malformed base URI.
 */
final class ConfigurationException extends VultrException
{
}

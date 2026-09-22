<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Enum;

/**
 * IP families accepted by the reserved IP endpoints.
 */
enum IpType: string
{
    case V4 = 'v4';
    case V6 = 'v6';
}

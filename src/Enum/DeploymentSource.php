<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Enum;

/**
 * What a new instance is built from.
 *
 * Exactly one source must be chosen when creating an instance;
 * {@see \BoudhraaDhia7\Vultr\Builder\InstanceBuilder} enforces that.
 */
enum DeploymentSource: string
{
    case OperatingSystem = 'os_id';
    case Iso = 'iso_id';
    case Snapshot = 'snapshot_id';
    case Application = 'app_id';
    case MarketplaceApp = 'image_id';
}

<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Cloud compute and bare metal plans.
 *
 * @see https://www.vultr.com/api/#tag/plans
 */
final class PlanResource extends AbstractResource
{
    /**
     * @param string|null $type   plan family, for example "vc2", "vhf", "vhp", "voc"
     * @param string|null $region restrict to plans available in one region
     */
    public function list(
        ?string $type = null,
        ?string $region = null,
        ?int $perPage = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('v2/plans', 'plans', $this->filterNulls([
            'type' => $type,
            'region' => $region,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(?string $type = null, ?string $region = null): Generator
    {
        yield from $this->paginate('v2/plans', 'plans', $this->filterNulls([
            'type' => $type,
            'region' => $region,
        ]));
    }

    public function listBareMetal(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/plans-metal', 'plans_metal', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function allBareMetal(): Generator
    {
        yield from $this->paginate('v2/plans-metal', 'plans_metal');
    }
}

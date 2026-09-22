<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Vultr regions and their plan availability.
 *
 * @see https://www.vultr.com/api/#tag/region
 */
final class RegionResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/regions', 'regions', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * Every region, paging transparently.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/regions', 'regions');
    }

    /**
     * Plan identifiers currently available in a region.
     *
     * @param string|null $type restrict to a plan family, for example "vc2" or "vhf"
     *
     * @return list<string>
     */
    public function availablePlans(string $regionId, ?string $type = null): array
    {
        $payload = $this->httpGet(
            sprintf('v2/regions/%s/availability', $this->segment($regionId, 'region id')),
            $this->filterNulls(['type' => $type]),
        );

        $plans = $payload['available_plans'] ?? [];

        return is_array($plans) ? array_values(array_map(strval(...), $plans)) : [];
    }
}

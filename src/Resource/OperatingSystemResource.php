<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * The operating system images Vultr can deploy.
 *
 * @see https://www.vultr.com/api/#tag/os
 */
final class OperatingSystemResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/os', 'os', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/os', 'os');
    }

    /**
     * Look up one operating system by id.
     *
     * @return array<string, mixed>|null null when no image carries that id
     */
    public function find(int $osId): ?array
    {
        foreach ($this->all() as $os) {
            if ((int) ($os['id'] ?? 0) === $osId) {
                return $os;
            }
        }

        return null;
    }

    /**
     * The display name of an operating system, for example "Ubuntu 24.04 LTS x64".
     */
    public function name(int $osId): ?string
    {
        $os = $this->find($osId);

        return isset($os['name']) ? (string) $os['name'] : null;
    }
}

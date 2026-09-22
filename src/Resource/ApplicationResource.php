<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * One-click and Marketplace applications.
 *
 * @see https://www.vultr.com/api/#tag/application
 */
final class ApplicationResource extends AbstractResource
{
    /**
     * @param string|null $type "all", "one-click" or "marketplace"
     */
    public function list(?string $type = null, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/applications', 'applications', $this->filterNulls([
            'type' => $type,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(?string $type = null): Generator
    {
        yield from $this->paginate('v2/applications', 'applications', $this->filterNulls(['type' => $type]));
    }
}

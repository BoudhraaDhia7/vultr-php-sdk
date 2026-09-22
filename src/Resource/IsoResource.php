<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Custom and public ISO images.
 *
 * @see https://www.vultr.com/api/#tag/iso
 */
final class IsoResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/iso', 'isos', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/iso', 'isos');
    }

    /**
     * The ISO images Vultr publishes for every account.
     */
    public function listPublic(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/iso-public', 'public_isos', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $isoId): array
    {
        return $this->unwrap($this->httpGet($this->path($isoId)), 'iso');
    }

    /**
     * Upload an ISO from a publicly reachable URL. Vultr fetches it
     * asynchronously; poll get() until its status is "complete".
     *
     * @return array<string, mixed>
     */
    public function createFromUrl(string $url): array
    {
        return $this->unwrap($this->httpPost('v2/iso', ['url' => $url]), 'iso');
    }

    public function delete(string $isoId): void
    {
        $this->httpDelete($this->path($isoId));
    }

    private function path(string $isoId): string
    {
        return 'v2/iso/'.$this->segment($isoId, 'ISO id');
    }
}

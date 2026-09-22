<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Instance snapshots.
 *
 * @see https://www.vultr.com/api/#tag/snapshot
 */
final class SnapshotResource extends AbstractResource
{
    public function list(?string $description = null, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/snapshots', 'snapshots', $this->filterNulls([
            'description' => $description,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/snapshots', 'snapshots');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $snapshotId): array
    {
        return $this->unwrap($this->httpGet($this->path($snapshotId)), 'snapshot');
    }

    /**
     * Snapshot a running instance.
     *
     * @return array<string, mixed>
     */
    public function create(string $instanceId, ?string $description = null): array
    {
        return $this->unwrap($this->httpPost('v2/snapshots', $this->filterNulls([
            'instance_id' => $instanceId,
            'description' => $description,
        ])), 'snapshot');
    }

    /**
     * Import a raw disk image from a publicly reachable URL.
     *
     * @return array<string, mixed>
     */
    public function createFromUrl(string $url, ?string $description = null): array
    {
        return $this->unwrap($this->httpPost('v2/snapshots/create-from-url', $this->filterNulls([
            'url' => $url,
            'description' => $description,
        ])), 'snapshot');
    }

    public function updateDescription(string $snapshotId, string $description): void
    {
        $this->httpPut($this->path($snapshotId), ['description' => $description]);
    }

    public function delete(string $snapshotId): void
    {
        $this->httpDelete($this->path($snapshotId));
    }

    private function path(string $snapshotId): string
    {
        return 'v2/snapshots/'.$this->segment($snapshotId, 'snapshot id');
    }
}

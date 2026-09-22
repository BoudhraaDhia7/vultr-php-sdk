<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * S3-compatible object storage subscriptions.
 *
 * @see https://www.vultr.com/api/#tag/s3
 */
final class ObjectStorageResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/object-storage', 'object_storages', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/object-storage', 'object_storages');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $objectStorageId): array
    {
        return $this->unwrap($this->httpGet($this->path($objectStorageId)), 'object_storage');
    }

    /**
     * The clusters a subscription can be created in.
     */
    public function listClusters(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/object-storage/clusters', 'clusters', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return array<string, mixed> includes the generated S3 credentials
     */
    public function create(int $clusterId, ?string $label = null): array
    {
        return $this->unwrap($this->httpPost('v2/object-storage', $this->filterNulls([
            'cluster_id' => $clusterId,
            'label' => $label,
        ])), 'object_storage');
    }

    public function setLabel(string $objectStorageId, string $label): void
    {
        $this->httpPut($this->path($objectStorageId), ['label' => $label]);
    }

    /**
     * Roll the S3 access and secret keys. The previous pair stops working
     * immediately, so update any clients before calling this.
     *
     * @return array<string, mixed> the new credentials
     */
    public function regenerateKeys(string $objectStorageId): array
    {
        return $this->unwrap(
            $this->httpPost($this->path($objectStorageId, 'regenerate-keys')),
            's3_credentials',
        );
    }

    public function delete(string $objectStorageId): void
    {
        $this->httpDelete($this->path($objectStorageId));
    }

    private function path(string $objectStorageId, string $suffix = ''): string
    {
        $path = 'v2/object-storage/'.$this->segment($objectStorageId, 'object storage id');

        return '' === $suffix ? $path : $path.'/'.$suffix;
    }
}

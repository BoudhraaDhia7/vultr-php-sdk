<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Block storage volumes.
 *
 * @see https://www.vultr.com/api/#tag/block
 */
final class BlockStorageResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/blocks', 'blocks', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/blocks', 'blocks');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $blockId): array
    {
        return $this->unwrap($this->httpGet($this->path($blockId)), 'block');
    }

    /**
     * @param int         $sizeGb    volume size in gigabytes
     * @param string|null $blockType "high_perf" or "storage_opt"
     *
     * @return array<string, mixed>
     */
    public function create(string $region, int $sizeGb, ?string $label = null, ?string $blockType = null): array
    {
        if ($sizeGb <= 0) {
            throw new ConfigurationException('A block storage volume needs a positive size in GB.');
        }

        return $this->unwrap($this->httpPost('v2/blocks', $this->filterNulls([
            'region' => $region,
            'size_gb' => $sizeGb,
            'label' => $label,
            'block_type' => $blockType,
        ])), 'block');
    }

    /**
     * Resize or relabel a volume. A volume can only grow, never shrink.
     */
    public function update(string $blockId, ?int $sizeGb = null, ?string $label = null): void
    {
        $changes = $this->filterNulls(['size_gb' => $sizeGb, 'label' => $label]);

        if ([] === $changes) {
            throw new ConfigurationException('update() needs a size or a label to change.');
        }

        $this->httpPatch($this->path($blockId), $changes);
    }

    /**
     * @param bool $live attach without rebooting the instance
     */
    public function attach(string $blockId, string $instanceId, bool $live = true): void
    {
        $this->httpAction($this->path($blockId, 'attach'), [
            'instance_id' => $instanceId,
            'live' => $live,
        ]);
    }

    public function detach(string $blockId, bool $live = true): void
    {
        $this->httpAction($this->path($blockId, 'detach'), ['live' => $live]);
    }

    public function delete(string $blockId): void
    {
        $this->httpDelete($this->path($blockId));
    }

    private function path(string $blockId, string $suffix = ''): string
    {
        $path = 'v2/blocks/'.$this->segment($blockId, 'block storage id');

        return '' === $suffix ? $path : $path.'/'.$suffix;
    }
}

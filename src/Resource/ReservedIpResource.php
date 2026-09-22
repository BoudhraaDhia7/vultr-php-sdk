<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Enum\IpType;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Reserved (floating) IP addresses.
 *
 * @see https://www.vultr.com/api/#tag/reserved-ip
 */
final class ReservedIpResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/reserved-ips', 'reserved_ips', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/reserved-ips', 'reserved_ips');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $reservedIpId): array
    {
        return $this->unwrap($this->httpGet($this->path($reservedIpId)), 'reserved_ip');
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $region, IpType $type, ?string $label = null): array
    {
        return $this->unwrap($this->httpPost('v2/reserved-ips', $this->filterNulls([
            'region' => $region,
            'ip_type' => $type->value,
            'label' => $label,
        ])), 'reserved_ip');
    }

    /**
     * Turn an instance's existing address into a reserved IP.
     *
     * @return array<string, mixed>
     */
    public function convert(string $ipAddress, ?string $label = null): array
    {
        return $this->unwrap($this->httpPost('v2/reserved-ips/convert', $this->filterNulls([
            'ip_address' => $ipAddress,
            'label' => $label,
        ])), 'reserved_ip');
    }

    public function setLabel(string $reservedIpId, string $label): void
    {
        $this->httpPatch($this->path($reservedIpId), ['label' => $label]);
    }

    public function attach(string $reservedIpId, string $instanceId): void
    {
        $this->httpAction($this->path($reservedIpId, 'attach'), ['instance_id' => $instanceId]);
    }

    public function detach(string $reservedIpId): void
    {
        $this->httpAction($this->path($reservedIpId, 'detach'));
    }

    public function delete(string $reservedIpId): void
    {
        $this->httpDelete($this->path($reservedIpId));
    }

    private function path(string $reservedIpId, string $suffix = ''): string
    {
        $path = 'v2/reserved-ips/'.$this->segment($reservedIpId, 'reserved IP id');

        return '' === $suffix ? $path : $path.'/'.$suffix;
    }
}

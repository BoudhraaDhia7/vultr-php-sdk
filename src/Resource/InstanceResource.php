<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Builder\InstanceBuilder;
use BoudhraaDhia7\Vultr\Enum\BackupScheduleType;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Cloud compute instances.
 *
 * Every method takes the instance id explicitly. The client holds no "current
 * instance" state, so one call can never leak into the next.
 *
 * @see https://www.vultr.com/api/#tag/instances
 */
final class InstanceResource extends AbstractResource
{
    /**
     * @param array<string, mixed> $filters supports label, tag, region, main_ip, hostname
     */
    public function list(array $filters = [], ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/instances', 'instances', $this->filterNulls([
            ...$filters,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * Every instance, paging transparently.
     *
     * @param array<string, mixed> $filters
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function all(array $filters = []): Generator
    {
        yield from $this->paginate('v2/instances', 'instances', $this->filterNulls($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $instanceId): array
    {
        return $this->unwrap($this->httpGet($this->path($instanceId)), 'instance');
    }

    /**
     * Create an instance.
     *
     * @param InstanceBuilder|array<string, mixed> $instance
     *
     * @return array<string, mixed> the created instance, including its id
     */
    public function create(InstanceBuilder|array $instance): array
    {
        $payload = $instance instanceof InstanceBuilder ? $instance->toArray() : $instance;

        return $this->unwrap($this->httpPost('v2/instances', $payload), 'instance');
    }

    /**
     * Update mutable fields in one request.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    public function update(string $instanceId, array $changes): array
    {
        if ([] === $changes) {
            throw new ConfigurationException('update() needs at least one field to change.');
        }

        return $this->unwrap($this->httpPatch($this->path($instanceId), $changes), 'instance');
    }

    public function delete(string $instanceId): void
    {
        $this->httpDelete($this->path($instanceId));
    }

    public function start(string $instanceId): void
    {
        $this->httpAction($this->path($instanceId, 'start'));
    }

    public function halt(string $instanceId): void
    {
        $this->httpAction($this->path($instanceId, 'halt'));
    }

    public function reboot(string $instanceId): void
    {
        $this->httpAction($this->path($instanceId, 'reboot'));
    }

    /**
     * Reinstall the instance, optionally changing its hostname.
     *
     * @return array<string, mixed>
     */
    public function reinstall(string $instanceId, ?string $hostname = null): array
    {
        return $this->unwrap(
            $this->httpPost($this->path($instanceId, 'reinstall'), $this->filterNulls(['hostname' => $hostname])),
            'instance',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setLabel(string $instanceId, string $label): array
    {
        return $this->update($instanceId, ['label' => $label]);
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    public function setTags(string $instanceId, array $tags): array
    {
        return $this->update($instanceId, ['tags' => array_values($tags)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function resize(string $instanceId, string $planId): array
    {
        return $this->update($instanceId, ['plan' => $planId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function changeOperatingSystem(string $instanceId, int $osId): array
    {
        return $this->update($instanceId, ['os_id' => $osId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function changeApplication(string $instanceId, int $appId): array
    {
        return $this->update($instanceId, ['app_id' => $appId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function setFirewallGroup(string $instanceId, string $firewallGroupId): array
    {
        return $this->update($instanceId, ['firewall_group_id' => $firewallGroupId]);
    }

    /**
     * Upgrades available to this instance.
     *
     * @param string|null $type "all", "applications", "os" or "plans"
     *
     * @return array<string, mixed>
     */
    public function availableUpgrades(string $instanceId, ?string $type = null): array
    {
        return $this->unwrap(
            $this->httpGet($this->path($instanceId, 'upgrades'), $this->filterNulls(['type' => $type])),
            'upgrades',
        );
    }

    /**
     * Bandwidth usage for the instance's current billing period.
     *
     * @return array<string, mixed>
     */
    public function bandwidth(string $instanceId): array
    {
        return $this->unwrap($this->httpGet($this->path($instanceId, 'bandwidth')), 'bandwidth');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function neighbors(string $instanceId): array
    {
        return $this->unwrapList($this->httpGet($this->path($instanceId, 'neighbors')), 'neighbors');
    }

    /**
     * The cloud-init user data, base64-encoded exactly as the API returns it.
     *
     * @return array<string, mixed>
     */
    public function userData(string $instanceId): array
    {
        return $this->unwrap($this->httpGet($this->path($instanceId, 'user-data')), 'user_data');
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreFromBackup(string $instanceId, string $backupId): array
    {
        return $this->httpPost($this->path($instanceId, 'restore'), ['backup_id' => $backupId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreFromSnapshot(string $instanceId, string $snapshotId): array
    {
        return $this->httpPost($this->path($instanceId, 'restore'), ['snapshot_id' => $snapshotId]);
    }

    // -- Addresses ---------------------------------------------------------

    public function listIpv4(string $instanceId, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($instanceId, 'ipv4'), 'ipv4s', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    public function listIpv6(string $instanceId, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($instanceId, 'ipv6'), 'ipv6s', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * Add an IPv4 address to the instance.
     *
     * @param bool $reboot reboot immediately so the guest OS picks the address up
     *
     * @return array<string, mixed>
     */
    public function createIpv4(string $instanceId, bool $reboot = false): array
    {
        return $this->unwrap(
            $this->httpPost($this->path($instanceId, 'ipv4'), ['reboot' => $reboot]),
            'ipv4',
        );
    }

    public function deleteIpv4(string $instanceId, string $ip): void
    {
        $this->httpDelete($this->path($instanceId, 'ipv4/'.$this->segment($ip, 'IP address')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReverseIpv4(string $instanceId): array
    {
        return $this->unwrapList($this->httpGet($this->path($instanceId, 'ipv4/reverse')), 'reverse_ipv4s');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReverseIpv6(string $instanceId): array
    {
        return $this->unwrapList($this->httpGet($this->path($instanceId, 'ipv6/reverse')), 'reverse_ipv6s');
    }

    public function setReverseIpv4(string $instanceId, string $ip, string $reverse): void
    {
        $this->httpAction($this->path($instanceId, 'ipv4/reverse'), ['ip' => $ip, 'reverse' => $reverse]);
    }

    public function setReverseIpv6(string $instanceId, string $ip, string $reverse): void
    {
        $this->httpAction($this->path($instanceId, 'ipv6/reverse'), ['ip' => $ip, 'reverse' => $reverse]);
    }

    /**
     * Reset an IPv4 reverse record back to Vultr's default.
     */
    public function resetReverseIpv4(string $instanceId, string $ip): void
    {
        $this->httpAction($this->path($instanceId, 'ipv4/reverse/default'), ['ip' => $ip]);
    }

    public function deleteReverseIpv6(string $instanceId, string $ip): void
    {
        $this->httpDelete($this->path($instanceId, 'ipv6/reverse/'.$this->segment($ip, 'IP address')));
    }

    // -- Backups -----------------------------------------------------------

    public function enableBackups(string $instanceId): void
    {
        $this->update($instanceId, ['backups' => 'enabled']);
    }

    public function disableBackups(string $instanceId): void
    {
        $this->update($instanceId, ['backups' => 'disabled']);
    }

    /**
     * @return array<string, mixed>
     */
    public function backupSchedule(string $instanceId): array
    {
        return $this->httpGet($this->path($instanceId, 'backup-schedule'));
    }

    /**
     * Set the automatic backup schedule.
     *
     * @param int|null $hour       UTC hour, 0-23
     * @param int|null $dayOfWeek  0 (Sunday) to 6, required for a weekly schedule
     * @param int|null $dayOfMonth 1-28, required for a monthly schedule
     *
     * @return array<string, mixed>
     */
    public function setBackupSchedule(
        string $instanceId,
        BackupScheduleType $type,
        ?int $hour = null,
        ?int $dayOfWeek = null,
        ?int $dayOfMonth = null,
    ): array {
        if ($type->requiresDayOfWeek() && null === $dayOfWeek) {
            throw new ConfigurationException('A weekly backup schedule needs a day of week.');
        }

        if ($type->requiresDayOfMonth() && null === $dayOfMonth) {
            throw new ConfigurationException('A monthly backup schedule needs a day of month.');
        }

        return $this->httpPost($this->path($instanceId, 'backup-schedule'), $this->filterNulls([
            'type' => $type->value,
            'hour' => $hour,
            'dow' => $dayOfWeek,
            'dom' => $dayOfMonth,
        ]));
    }

    // -- ISO ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function isoStatus(string $instanceId): array
    {
        return $this->unwrap($this->httpGet($this->path($instanceId, 'iso')), 'iso_status');
    }

    /**
     * @return array<string, mixed>
     */
    public function attachIso(string $instanceId, string $isoId): array
    {
        return $this->httpPost($this->path($instanceId, 'iso/attach'), ['iso_id' => $isoId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function detachIso(string $instanceId): array
    {
        return $this->httpPost($this->path($instanceId, 'iso/detach'));
    }

    // -- VPC ---------------------------------------------------------------

    public function listVpcs(string $instanceId, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($instanceId, 'vpcs'), 'vpcs', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    public function attachVpc(string $instanceId, string $vpcId): void
    {
        $this->httpAction($this->path($instanceId, 'vpcs/attach'), ['vpc_id' => $vpcId]);
    }

    public function detachVpc(string $instanceId, string $vpcId): void
    {
        $this->httpAction($this->path($instanceId, 'vpcs/detach'), ['vpc_id' => $vpcId]);
    }

    private function path(string $instanceId, string $suffix = ''): string
    {
        $path = 'v2/instances/'.$this->segment($instanceId, 'instance id');

        return '' === $suffix ? $path : $path.'/'.$suffix;
    }
}

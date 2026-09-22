<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Firewall groups and their rules.
 *
 * Attach a group to an instance with
 * {@see \BoudhraaDhia7\Vultr\Resource\InstanceResource::setFirewallGroup()}.
 *
 * @see https://www.vultr.com/api/#tag/firewall
 */
final class FirewallResource extends AbstractResource
{
    public function listGroups(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/firewalls', 'firewall_groups', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function allGroups(): Generator
    {
        yield from $this->paginate('v2/firewalls', 'firewall_groups');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $groupId): array
    {
        return $this->unwrap($this->httpGet($this->path($groupId)), 'firewall_group');
    }

    /**
     * @return array<string, mixed>
     */
    public function createGroup(?string $description = null): array
    {
        return $this->unwrap(
            $this->httpPost('v2/firewalls', $this->filterNulls(['description' => $description])),
            'firewall_group',
        );
    }

    public function updateGroup(string $groupId, string $description): void
    {
        $this->httpPut($this->path($groupId), ['description' => $description]);
    }

    public function deleteGroup(string $groupId): void
    {
        $this->httpDelete($this->path($groupId));
    }

    // -- Rules -------------------------------------------------------------

    public function listRules(string $groupId, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($groupId, 'rules'), 'firewall_rules', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function allRules(string $groupId): Generator
    {
        yield from $this->paginate($this->path($groupId, 'rules'), 'firewall_rules');
    }

    /**
     * @return array<string, mixed>
     */
    public function getRule(string $groupId, string $ruleId): array
    {
        return $this->unwrap(
            $this->httpGet($this->path($groupId, 'rules/'.$this->segment($ruleId, 'rule id'))),
            'firewall_rule',
        );
    }

    /**
     * Add a rule to a group.
     *
     * @param string      $ipType     "v4" or "v6"
     * @param string      $protocol   "ICMP", "TCP", "UDP", "GRE", "ESP" or "AH"
     * @param string      $subnet     network address, for example "192.0.2.0"
     * @param int         $subnetSize CIDR prefix length, for example 24
     * @param string|null $port       a single port ("22") or a range ("8000:8080")
     * @param string|null $source     "cloudflare" to allow Cloudflare's ranges instead of a subnet
     *
     * @return array<string, mixed>
     */
    public function createRule(
        string $groupId,
        string $ipType,
        string $protocol,
        string $subnet,
        int $subnetSize,
        ?string $port = null,
        ?string $source = null,
        ?string $notes = null,
    ): array {
        if ($subnetSize < 0) {
            throw new ConfigurationException('A firewall rule subnet size cannot be negative.');
        }

        return $this->unwrap($this->httpPost($this->path($groupId, 'rules'), $this->filterNulls([
            'ip_type' => $ipType,
            'protocol' => $protocol,
            'subnet' => $subnet,
            'subnet_size' => $subnetSize,
            'port' => $port,
            'source' => $source,
            'notes' => $notes,
        ])), 'firewall_rule');
    }

    public function deleteRule(string $groupId, string $ruleId): void
    {
        $this->httpDelete($this->path($groupId, 'rules/'.$this->segment($ruleId, 'rule id')));
    }

    private function path(string $groupId, string $suffix = ''): string
    {
        $path = 'v2/firewalls/'.$this->segment($groupId, 'firewall group id');

        return '' === $suffix ? $path : $path.'/'.$suffix;
    }
}

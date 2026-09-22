<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Enum\DnsRecordType;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Managed DNS: domains, records, SOA and DNSSEC.
 *
 * @see https://www.vultr.com/api/#tag/dns
 */
final class DomainResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/domains', 'domains', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/domains', 'domains');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $domain): array
    {
        return $this->unwrap($this->httpGet($this->path($domain)), 'dns_domain');
    }

    /**
     * Create a DNS zone.
     *
     * @param string|null $ip         seeds default A and MX records; omit to create an empty zone
     * @param bool        $dnssec     enable DNSSEC for the new zone
     * @param string|null $dnsSecMode overrides $dnssec when given: "enabled" or "disabled"
     *
     * @return array<string, mixed>
     */
    public function create(
        string $domain,
        ?string $ip = null,
        bool $dnssec = false,
        ?string $dnsSecMode = null,
    ): array {
        return $this->unwrap($this->httpPost('v2/domains', $this->filterNulls([
            'domain' => $domain,
            'ip' => $ip,
            'dns_sec' => $dnsSecMode ?? ($dnssec ? 'enabled' : 'disabled'),
        ])), 'dns_domain');
    }

    public function delete(string $domain): void
    {
        $this->httpDelete($this->path($domain));
    }

    /**
     * Toggle DNSSEC on an existing zone.
     */
    public function setDnssec(string $domain, bool $enabled): void
    {
        $this->httpPut($this->path($domain), ['dns_sec' => $enabled ? 'enabled' : 'disabled']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dnssecInfo(string $domain): array
    {
        return $this->unwrapList($this->httpGet($this->path($domain, 'dnssec')), 'dns_records');
    }

    /**
     * @return array<string, mixed>
     */
    public function soa(string $domain): array
    {
        return $this->unwrap($this->httpGet($this->path($domain, 'soa')), 'dns_soa');
    }

    public function updateSoa(string $domain, ?string $primaryNameserver = null, ?string $email = null): void
    {
        $changes = $this->filterNulls(['nsprimary' => $primaryNameserver, 'email' => $email]);

        if ([] === $changes) {
            throw new ConfigurationException('updateSoa() needs a primary nameserver or an email address.');
        }

        $this->httpPatch($this->path($domain, 'soa'), $changes);
    }

    // -- Records -----------------------------------------------------------

    public function listRecords(string $domain, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($domain, 'records'), 'records', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function allRecords(string $domain): Generator
    {
        yield from $this->paginate($this->path($domain, 'records'), 'records');
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecord(string $domain, string $recordId): array
    {
        return $this->unwrap(
            $this->httpGet($this->path($domain, 'records/'.$this->segment($recordId, 'record id'))),
            'record',
        );
    }

    /**
     * Create a DNS record.
     *
     * @param string   $name     subdomain, or an empty string for the zone apex
     * @param int|null $ttl      seconds; Vultr's default applies when omitted
     * @param int|null $priority required for MX and SRV records
     *
     * @return array<string, mixed>
     */
    public function createRecord(
        string $domain,
        string $name,
        DnsRecordType $type,
        string $data,
        ?int $ttl = null,
        ?int $priority = null,
    ): array {
        if (in_array($type, [DnsRecordType::MX, DnsRecordType::SRV], true) && null === $priority) {
            throw new ConfigurationException(sprintf('A %s record needs a priority.', $type->value));
        }

        return $this->unwrap($this->httpPost($this->path($domain, 'records'), $this->filterNulls([
            'name' => $name,
            'type' => $type->value,
            'data' => $data,
            'ttl' => $ttl,
            'priority' => $priority,
        ])), 'record');
    }

    public function updateRecord(
        string $domain,
        string $recordId,
        ?string $name = null,
        ?string $data = null,
        ?int $ttl = null,
        ?int $priority = null,
    ): void {
        $changes = $this->filterNulls([
            'name' => $name,
            'data' => $data,
            'ttl' => $ttl,
            'priority' => $priority,
        ]);

        if ([] === $changes) {
            throw new ConfigurationException('updateRecord() needs at least one field to change.');
        }

        $this->httpPatch($this->path($domain, 'records/'.$this->segment($recordId, 'record id')), $changes);
    }

    public function deleteRecord(string $domain, string $recordId): void
    {
        $this->httpDelete($this->path($domain, 'records/'.$this->segment($recordId, 'record id')));
    }

    private function path(string $domain, string $suffix = ''): string
    {
        $path = 'v2/domains/'.$this->segment($domain, 'domain');

        return '' === $suffix ? $path : $path.'/'.$suffix;
    }
}

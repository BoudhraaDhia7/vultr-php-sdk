<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Enum\DnsRecordType;
use BoudhraaDhia7\Vultr\Enum\IpType;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Resource\AccountResource;
use BoudhraaDhia7\Vultr\Resource\BlockStorageResource;
use BoudhraaDhia7\Vultr\Resource\DomainResource;
use BoudhraaDhia7\Vultr\Resource\ObjectStorageResource;
use BoudhraaDhia7\Vultr\Resource\OperatingSystemResource;
use BoudhraaDhia7\Vultr\Resource\RegionResource;
use BoudhraaDhia7\Vultr\Resource\ReservedIpResource;
use BoudhraaDhia7\Vultr\Resource\SnapshotResource;
use BoudhraaDhia7\Vultr\Resource\StartupScriptResource;
use BoudhraaDhia7\Vultr\Resource\UserResource;
use BoudhraaDhia7\Vultr\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the endpoints that the v1 client addressed incorrectly, plus the
 * payload shapes that are easy to get wrong.
 */
#[CoversClass(AccountResource::class)]
#[CoversClass(BlockStorageResource::class)]
#[CoversClass(DomainResource::class)]
#[CoversClass(ObjectStorageResource::class)]
#[CoversClass(OperatingSystemResource::class)]
#[CoversClass(RegionResource::class)]
#[CoversClass(ReservedIpResource::class)]
#[CoversClass(SnapshotResource::class)]
#[CoversClass(StartupScriptResource::class)]
#[CoversClass(UserResource::class)]
final class ResourceRoutingTest extends TestCase
{
    public function testAccountRemainingCreditUsesTheDecodedPayload(): void
    {
        $this->http->queueJson(['account' => ['balance' => -25.5, 'pending_charges' => 5.25]]);

        self::assertSame(20.25, $this->client()->account()->remainingCredit());
    }

    public function testOperatingSystemNameLooksUpADecodedList(): void
    {
        $this->http->queueJson([
            'os' => [
                ['id' => 1743, 'name' => 'Ubuntu 22.04 LTS x64'],
                ['id' => 2136, 'name' => 'Debian 12 x64'],
            ],
            'meta' => ['links' => []],
        ]);

        self::assertSame('Ubuntu 22.04 LTS x64', $this->client()->operatingSystems()->name(1743));
    }

    public function testOperatingSystemNameReturnsNullWhenTheIdIsUnknown(): void
    {
        $this->http->queueJson(['os' => [['id' => 1743, 'name' => 'Ubuntu']], 'meta' => ['links' => []]]);

        self::assertNull($this->client()->operatingSystems()->name(9999));
    }

    public function testRegionAvailabilityReturnsPlanIdentifiers(): void
    {
        $this->http->queueJson(['available_plans' => ['vc2-1c-1gb', 'vhf-1c-1gb']]);

        $plans = $this->client()->regions()->availablePlans('ewr', 'vc2');

        self::assertSame(['vc2-1c-1gb', 'vhf-1c-1gb'], $plans);
        $this->assertRequestPath('v2/regions/ewr/availability?type=vc2');
    }

    public function testSnapshotCreateSendsTheInstanceId(): void
    {
        $this->http->queueJson(['snapshot' => ['id' => 'snap-1']]);

        $this->client()->snapshots()->create('abc', 'nightly');

        $this->assertRequestPath('v2/snapshots');
        self::assertSame(['instance_id' => 'abc', 'description' => 'nightly'], $this->http->bodyAt(0));
    }

    public function testSnapshotCreateFromUrlUsesThePluralPath(): void
    {
        $this->http->queueJson(['snapshot' => ['id' => 'snap-1']]);

        $this->client()->snapshots()->createFromUrl('https://example.test/disk.raw');

        $this->assertRequestPath('v2/snapshots/create-from-url');
    }

    public function testStartupScriptContentsAreBase64Encoded(): void
    {
        $this->http->queueJson(['startup_script' => ['id' => 'script-1']]);

        $this->client()->startupScripts()->create('bootstrap', "#!/bin/sh\napt-get update\n");

        $body = $this->http->bodyAt(0);

        self::assertSame('boot', $body['type']);
        self::assertSame("#!/bin/sh\napt-get update\n", base64_decode((string) $body['script'], true));
    }

    public function testStartupScriptDeleteUsesTheRestfulPath(): void
    {
        $this->http->queueNoContent();

        $this->client()->startupScripts()->delete('script-1');

        $this->assertRequestMethod('DELETE');
        $this->assertRequestPath('v2/startup-scripts/script-1');
    }

    public function testReservedIpCreateSendsTheIpFamily(): void
    {
        $this->http->queueJson(['reserved_ip' => ['id' => 'rip-1']]);

        $this->client()->reservedIps()->create('ewr', IpType::V4, 'failover');

        self::assertSame(
            ['region' => 'ewr', 'ip_type' => 'v4', 'label' => 'failover'],
            $this->http->bodyAt(0),
        );
    }

    public function testReservedIpAttachTakesTheInstanceExplicitly(): void
    {
        $this->http->queueNoContent();

        $this->client()->reservedIps()->attach('rip-1', 'abc');

        $this->assertRequestPath('v2/reserved-ips/rip-1/attach');
        self::assertSame(['instance_id' => 'abc'], $this->http->bodyAt(0));
    }

    public function testBlockStorageRejectsANonPositiveSize(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->client()->blockStorage()->create('ewr', 0);
    }

    public function testBlockStorageResizeUsesPatch(): void
    {
        $this->http->queueJson([]);

        $this->client()->blockStorage()->update('block-1', 100);

        $this->assertRequestMethod('PATCH');
        $this->assertRequestPath('v2/blocks/block-1');
        self::assertSame(['size_gb' => 100], $this->http->bodyAt(0));
    }

    public function testDomainCreateSendsIpAndDnssecMode(): void
    {
        $this->http->queueJson(['dns_domain' => ['domain' => 'example.test']]);

        $this->client()->domains()->create('example.test', '192.0.2.10', true);

        self::assertSame([
            'domain' => 'example.test',
            'ip' => '192.0.2.10',
            'dns_sec' => 'enabled',
        ], $this->http->bodyAt(0));
    }

    public function testDomainDeleteTargetsTheDomainPath(): void
    {
        $this->http->queueNoContent();

        $this->client()->domains()->delete('example.test');

        $this->assertRequestMethod('DELETE');
        $this->assertRequestPath('v2/domains/example.test');
        self::assertNull($this->http->lastRequest()->body());
    }

    public function testDnsRecordCreateUsesThePluralRecordsPath(): void
    {
        $this->http->queueJson(['record' => ['id' => 'rec-1']]);

        $this->client()->domains()->createRecord(
            'example.test',
            'www',
            DnsRecordType::A,
            '192.0.2.10',
            300,
        );

        $this->assertRequestPath('v2/domains/example.test/records');
        self::assertSame([
            'name' => 'www',
            'type' => 'A',
            'data' => '192.0.2.10',
            'ttl' => 300,
        ], $this->http->bodyAt(0));
    }

    public function testAnMxRecordRequiresAPriority(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('priority');

        $this->client()->domains()->createRecord('example.test', '', DnsRecordType::MX, 'mail.example.test');
    }

    public function testUserDeleteHasNoTrailingWhitespaceInThePath(): void
    {
        $this->http->queueNoContent();

        $this->client()->users()->delete('user-1');

        $this->assertRequestPath('v2/users/user-1');
    }

    public function testUserUpdateOnlySendsTheFieldsThatWereGiven(): void
    {
        $this->http->queueJson([]);

        $this->client()->users()->update('user-1', name: 'Dhia', apiEnabled: true);

        self::assertSame(['name' => 'Dhia', 'api_enabled' => true], $this->http->bodyAt(0));
    }

    public function testObjectStorageRegenerateKeysReturnsTheNewCredentials(): void
    {
        $this->http->queueJson(['s3_credentials' => ['s3_access_key' => 'AK', 's3_secret_key' => 'SK']]);

        $credentials = $this->client()->objectStorage()->regenerateKeys('obj-1');

        self::assertSame('AK', $credentials['s3_access_key']);
        $this->assertRequestPath('v2/object-storage/obj-1/regenerate-keys');
    }
}

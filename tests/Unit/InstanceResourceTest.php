<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Builder\InstanceBuilder;
use BoudhraaDhia7\Vultr\Enum\BackupScheduleType;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Resource\InstanceResource;
use BoudhraaDhia7\Vultr\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InstanceResource::class)]
final class InstanceResourceTest extends TestCase
{
    public function testGetUnwrapsTheInstanceEnvelope(): void
    {
        $this->http->queueJson(['instance' => ['id' => 'abc', 'label' => 'web-01']]);

        $instance = $this->client()->instances()->get('abc');

        self::assertSame('web-01', $instance['label']);
        $this->assertRequestMethod('GET');
        $this->assertRequestPath('v2/instances/abc');
    }

    public function testAnEmptyInstanceIdIsRejectedBeforeAnyRequestIsSent(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('instance id');

        try {
            $this->client()->instances()->get('   ');
        } finally {
            self::assertSame(0, $this->http->requestCount());
        }
    }

    public function testInstanceIdsAreUrlEncoded(): void
    {
        $this->http->queueJson(['instance' => []]);

        $this->client()->instances()->get('a b/c');

        $this->assertRequestPath('v2/instances/a%20b%2Fc');
    }

    /**
     * The v1 client kept a "current instance" on the object, so one call to an
     * instance method made every later call require that id. State now lives in
     * the arguments only.
     */
    public function testInstanceCallsDoNotLeakStateIntoLaterCalls(): void
    {
        $this->http
            ->queueJson(['instance' => ['id' => 'abc']])
            ->queueJson(['regions' => [], 'meta' => []]);

        $client = $this->client();
        $client->instances()->get('abc');
        $client->regions()->list();

        $this->assertRequestPath('v2/instances/abc', 0);
        $this->assertRequestPath('v2/regions', 1);
    }

    public function testCreateSendsTheBuilderPayload(): void
    {
        $this->http->queueJson(['instance' => ['id' => 'new-id']], 202);

        $instance = $this->client()->instances()->create(
            InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->fromOperatingSystem(1743)->label('web-01'),
        );

        self::assertSame('new-id', $instance['id']);
        $this->assertRequestMethod('POST');
        $this->assertRequestPath('v2/instances');
        self::assertSame([
            'region' => 'ewr',
            'plan' => 'vc2-1c-1gb',
            'os_id' => 1743,
            'label' => 'web-01',
        ], $this->http->bodyAt(0));
    }

    public function testCreateAlsoAcceptsAPlainArray(): void
    {
        $this->http->queueJson(['instance' => ['id' => 'new-id']], 202);

        $this->client()->instances()->create(['region' => 'ewr', 'plan' => 'vc2-1c-1gb', 'os_id' => 1743]);

        self::assertSame('ewr', $this->http->bodyAt(0)['region']);
    }

    public function testPowerActionsPostToTheirEndpoints(): void
    {
        $this->http->queueNoContent()->queueNoContent()->queueNoContent();

        $instances = $this->client()->instances();
        $instances->start('abc');
        $instances->halt('abc');
        $instances->reboot('abc');

        $this->assertRequestPath('v2/instances/abc/start', 0);
        $this->assertRequestPath('v2/instances/abc/halt', 1);
        $this->assertRequestPath('v2/instances/abc/reboot', 2);
        self::assertSame('POST', $this->http->requestAt(0)->method());
    }

    public function testDeleteUsesTheDeleteMethodAndNoTrailingWhitespace(): void
    {
        $this->http->queueNoContent();

        $this->client()->instances()->delete('abc');

        $this->assertRequestMethod('DELETE');
        $this->assertRequestPath('v2/instances/abc');
        self::assertNull($this->http->lastRequest()->body());
    }

    public function testUpdateRejectsAnEmptyChangeSet(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->client()->instances()->update('abc', []);
    }

    public function testBackupTogglesSendTheStringsTheApiExpects(): void
    {
        $this->http->queueJson(['instance' => []])->queueJson(['instance' => []]);

        $instances = $this->client()->instances();
        $instances->enableBackups('abc');
        $instances->disableBackups('abc');

        self::assertSame(['backups' => 'enabled'], $this->http->bodyAt(0));
        self::assertSame(['backups' => 'disabled'], $this->http->bodyAt(1));
        self::assertSame('PATCH', $this->http->requestAt(0)->method());
    }

    public function testAWeeklyBackupScheduleRequiresADayOfWeek(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('day of week');

        $this->client()->instances()->setBackupSchedule('abc', BackupScheduleType::Weekly, 3);
    }

    public function testAMonthlyBackupScheduleRequiresADayOfMonth(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('day of month');

        $this->client()->instances()->setBackupSchedule('abc', BackupScheduleType::Monthly, 3);
    }

    public function testADailyScheduleOmitsTheUnusedDayFields(): void
    {
        $this->http->queueJson([]);

        $this->client()->instances()->setBackupSchedule('abc', BackupScheduleType::Daily, 4);

        self::assertSame(['type' => 'daily', 'hour' => 4], $this->http->bodyAt(0));
    }

    public function testCreateIpv4HitsThePluralInstancesPath(): void
    {
        $this->http->queueJson(['ipv4' => ['ip' => '192.0.2.10']]);

        $this->client()->instances()->createIpv4('abc', true);

        $this->assertRequestPath('v2/instances/abc/ipv4');
        self::assertSame(['reboot' => true], $this->http->bodyAt(0));
    }

    public function testDeleteIpv4HitsThePluralInstancesPath(): void
    {
        $this->http->queueNoContent();

        $this->client()->instances()->deleteIpv4('abc', '192.0.2.10');

        $this->assertRequestMethod('DELETE');
        $this->assertRequestPath('v2/instances/abc/ipv4/192.0.2.10');
    }

    public function testListPassesFiltersAsQueryParameters(): void
    {
        $this->http->queueJson(['instances' => [], 'meta' => ['total' => 0, 'links' => []]]);

        $this->client()->instances()->list(['region' => 'ewr', 'label' => 'web-01'], 50);

        $this->assertRequestPath('v2/instances?region=ewr&label=web-01&per_page=50');
    }

    public function testAllFollowsCursorsUntilTheyRunOut(): void
    {
        $this->http
            ->queueJson([
                'instances' => [['id' => 'a'], ['id' => 'b']],
                'meta' => ['total' => 3, 'links' => ['next' => 'cursor-2']],
            ])
            ->queueJson([
                'instances' => [['id' => 'c']],
                'meta' => ['total' => 3, 'links' => ['next' => '']],
            ]);

        $ids = [];

        foreach ($this->client()->instances()->all() as $instance) {
            $ids[] = $instance['id'];
        }

        self::assertSame(['a', 'b', 'c'], $ids);
        self::assertSame(2, $this->http->requestCount());
        self::assertStringContainsString('cursor=cursor-2', $this->http->requestAt(1)->uri());
    }

    public function testAllStopsFetchingWhenTheCallerBreaksOutEarly(): void
    {
        $this->http->queueJson([
            'instances' => [['id' => 'a'], ['id' => 'b']],
            'meta' => ['links' => ['next' => 'cursor-2']],
        ]);

        foreach ($this->client()->instances()->all() as $instance) {
            self::assertSame('a', $instance['id']);

            break;
        }

        self::assertSame(1, $this->http->requestCount());
    }

    public function testAllStopsOnAnEmptyPageThatStillAdvertisesACursor(): void
    {
        $this->http->queueJson([
            'instances' => [],
            'meta' => ['links' => ['next' => 'cursor-2']],
        ]);

        self::assertSame([], iterator_to_array($this->client()->instances()->all()));
        self::assertSame(1, $this->http->requestCount());
    }
}

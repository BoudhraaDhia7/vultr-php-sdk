<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Builder\InstanceBuilder;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstanceBuilder::class)]
final class InstanceBuilderTest extends TestCase
{
    public function testItBuildsAMinimalPayload(): void
    {
        $payload = InstanceBuilder::in('ewr')
            ->plan('vc2-1c-1gb')
            ->fromOperatingSystem(1743)
            ->toArray();

        self::assertSame([
            'region' => 'ewr',
            'plan' => 'vc2-1c-1gb',
            'os_id' => 1743,
        ], $payload);
    }

    public function testEachCallReturnsANewBuilderSoTemplatesCanBeReused(): void
    {
        $template = InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->fromOperatingSystem(1743);

        $web = $template->label('web-01')->toArray();
        $api = $template->label('api-01')->toArray();

        self::assertSame('web-01', $web['label']);
        self::assertSame('api-01', $api['label']);
        self::assertArrayNotHasKey('label', $template->toArray());
    }

    public function testRegionIsRequired(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('without a region');

        InstanceBuilder::fromArray(['plan' => 'vc2-1c-1gb', 'os_id' => 1743])->toArray();
    }

    public function testPlanIsRequired(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('without a plan');

        InstanceBuilder::in('ewr')->fromOperatingSystem(1743)->toArray();
    }

    public function testADeploymentSourceIsRequired(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('needs a deployment source');

        InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->toArray();
    }

    public function testChoosingANewSourceReplacesThePreviousOne(): void
    {
        $payload = InstanceBuilder::in('ewr')
            ->plan('vc2-1c-1gb')
            ->fromSnapshot('snap-1')
            ->fromOperatingSystem(1743)
            ->toArray();

        self::assertArrayNotHasKey('snapshot_id', $payload);
        self::assertSame(1743, $payload['os_id']);
    }

    public function testTwoSourcesSetByHandAreRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('exactly one deployment source');

        InstanceBuilder::fromArray([
            'region' => 'ewr',
            'plan' => 'vc2-1c-1gb',
            'snapshot_id' => 'snap-1',
            'iso_id' => 'iso-1',
        ])->toArray();
    }

    public function testIsoDeploymentSetsTheCustomOsId(): void
    {
        $payload = InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->fromIso('iso-1')->toArray();

        self::assertSame('iso-1', $payload['iso_id']);
        self::assertSame(159, $payload['os_id']);
    }

    public function testApplicationDeploymentSetsTheApplicationOsId(): void
    {
        $payload = InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->fromApplication(37)->toArray();

        self::assertSame(37, $payload['app_id']);
        self::assertSame(186, $payload['os_id']);
    }

    public function testUserDataIsBase64Encoded(): void
    {
        $payload = InstanceBuilder::in('ewr')
            ->plan('vc2-1c-1gb')
            ->fromOperatingSystem(1743)
            ->userData("#cloud-config\npackages:\n  - nginx\n")
            ->toArray();

        self::assertSame(
            "#cloud-config\npackages:\n  - nginx\n",
            base64_decode((string) $payload['user_data'], true),
        );
    }

    public function testOptionalFlagsUseTheValuesTheApiExpects(): void
    {
        $payload = InstanceBuilder::in('ewr')
            ->plan('vc2-1c-1gb')
            ->fromOperatingSystem(1743)
            ->enableIpv6()
            ->enableBackups()
            ->enableDdosProtection()
            ->hostname('web-01')
            ->tags(['web', 'prod'])
            ->sshKeys(['key-1'])
            ->toArray();

        self::assertTrue($payload['enable_ipv6']);
        self::assertSame('enabled', $payload['backups']);
        self::assertTrue($payload['ddos_protection']);
        self::assertSame('web-01', $payload['hostname']);
        self::assertSame(['web', 'prod'], $payload['tags']);
        self::assertSame(['key-1'], $payload['sshkey_id']);
    }

    public function testDisablingBackupsSendsTheDisabledString(): void
    {
        $payload = InstanceBuilder::in('ewr')
            ->plan('vc2-1c-1gb')
            ->fromOperatingSystem(1743)
            ->enableBackups(false)
            ->toArray();

        self::assertSame('disabled', $payload['backups']);
    }

    public function testSetAddsFieldsTheBuilderDoesNotModel(): void
    {
        $payload = InstanceBuilder::in('ewr')
            ->plan('vc2-1c-1gb')
            ->fromOperatingSystem(1743)
            ->set('activation_email', false)
            ->toArray();

        self::assertFalse($payload['activation_email']);
    }
}

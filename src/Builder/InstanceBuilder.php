<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Builder;

use BoudhraaDhia7\Vultr\Enum\DeploymentSource;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;

/**
 * Builds the payload for creating an instance.
 *
 * Every method returns a new builder, so a partially configured builder can be
 * kept as a template and specialised per instance:
 *
 *     $template = InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->enableIpv6();
 *     $web = $template->fromOperatingSystem(1743)->label('web-01');
 *     $api = $template->fromOperatingSystem(1743)->label('api-01');
 *
 * Required values (region, plan and exactly one deployment source) are checked
 * by {@see toArray()} before a request is ever sent.
 */
final class InstanceBuilder
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function __construct(private readonly array $attributes = [])
    {
    }

    /**
     * Start a builder for a region, for example "ewr" or "syd".
     */
    public static function in(string $region): self
    {
        return new self(['region' => $region]);
    }

    /**
     * Start from an existing payload, for values this builder does not cover.
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public function region(string $region): self
    {
        return $this->with(['region' => $region]);
    }

    /**
     * The plan identifier, for example "vc2-1c-1gb".
     */
    public function plan(string $plan): self
    {
        return $this->with(['plan' => $plan]);
    }

    public function fromOperatingSystem(int $osId): self
    {
        return $this->withSource(DeploymentSource::OperatingSystem, $osId);
    }

    public function fromSnapshot(string $snapshotId): self
    {
        return $this->withSource(DeploymentSource::Snapshot, $snapshotId);
    }

    /**
     * Boot from a custom ISO. Vultr requires the "custom" OS id alongside it.
     */
    public function fromIso(string $isoId): self
    {
        return $this->withSource(DeploymentSource::Iso, $isoId)->with(['os_id' => 159]);
    }

    /**
     * Deploy a one-click application by its numeric application id.
     */
    public function fromApplication(int $appId): self
    {
        return $this->withSource(DeploymentSource::Application, $appId)->with(['os_id' => 186]);
    }

    /**
     * Deploy a Marketplace application by its image id slug.
     */
    public function fromMarketplaceApp(string $imageId): self
    {
        return $this->withSource(DeploymentSource::MarketplaceApp, $imageId)->with(['os_id' => 186]);
    }

    public function label(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    public function hostname(string $hostname): self
    {
        return $this->with(['hostname' => $hostname]);
    }

    /**
     * @param list<string> $tags
     */
    public function tags(array $tags): self
    {
        return $this->with(['tags' => array_values($tags)]);
    }

    /**
     * @param list<string> $sshKeyIds
     */
    public function sshKeys(array $sshKeyIds): self
    {
        return $this->with(['sshkey_id' => array_values($sshKeyIds)]);
    }

    public function startupScript(string $scriptId): self
    {
        return $this->with(['script_id' => $scriptId]);
    }

    /**
     * Cloud-init user data. The SDK base64-encodes it, as the API requires.
     */
    public function userData(string $userData): self
    {
        return $this->with(['user_data' => base64_encode($userData)]);
    }

    public function firewallGroup(string $firewallGroupId): self
    {
        return $this->with(['firewall_group_id' => $firewallGroupId]);
    }

    public function reservedIp(string $reservedIpId): self
    {
        return $this->with(['reserved_ipv4' => $reservedIpId]);
    }

    public function enableIpv6(bool $enabled = true): self
    {
        return $this->with(['enable_ipv6' => $enabled]);
    }

    public function enableBackups(bool $enabled = true): self
    {
        return $this->with(['backups' => $enabled ? 'enabled' : 'disabled']);
    }

    public function enableDdosProtection(bool $enabled = true): self
    {
        return $this->with(['ddos_protection' => $enabled]);
    }

    public function enableVpc(bool $enabled = true): self
    {
        return $this->with(['enable_vpc' => $enabled]);
    }

    /**
     * @param list<string> $vpcIds
     */
    public function attachVpcs(array $vpcIds): self
    {
        return $this->with(['attach_vpc' => array_values($vpcIds)]);
    }

    public function ipxeChainUrl(string $url): self
    {
        return $this->with(['ipxe_chain_url' => $url]);
    }

    /**
     * Set any field this builder does not model explicitly.
     */
    public function set(string $key, mixed $value): self
    {
        return $this->with([$key => $value]);
    }

    /**
     * The payload, validated.
     *
     * @throws ConfigurationException when a required value is missing or two
     *                                deployment sources were given
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        foreach (['region', 'plan'] as $required) {
            if (!isset($this->attributes[$required]) || '' === $this->attributes[$required]) {
                throw new ConfigurationException(sprintf(
                    'An instance cannot be created without a %s.',
                    $required,
                ));
            }
        }

        $sources = array_values(array_filter(
            DeploymentSource::cases(),
            fn (DeploymentSource $source): bool => DeploymentSource::OperatingSystem !== $source
                && isset($this->attributes[$source->value]),
        ));

        if (count($sources) > 1) {
            throw new ConfigurationException(sprintf(
                'An instance has exactly one deployment source, but %s were given.',
                implode(', ', array_map(static fn (DeploymentSource $s): string => $s->value, $sources)),
            ));
        }

        if ([] === $sources && !isset($this->attributes[DeploymentSource::OperatingSystem->value])) {
            throw new ConfigurationException(
                'An instance needs a deployment source: call fromOperatingSystem(), fromSnapshot(), '
                .'fromIso(), fromApplication() or fromMarketplaceApp().',
            );
        }

        return $this->attributes;
    }

    private function withSource(DeploymentSource $source, string|int $id): self
    {
        $attributes = $this->attributes;

        // Swapping the source replaces any previously chosen one rather than
        // silently sending a payload the API would reject.
        foreach (DeploymentSource::cases() as $case) {
            unset($attributes[$case->value]);
        }

        $attributes[$source->value] = $id;

        return new self($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function with(array $attributes): self
    {
        return new self([...$this->attributes, ...$attributes]);
    }
}

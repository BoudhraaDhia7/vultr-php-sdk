<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr;

use BoudhraaDhia7\Vultr\Http\CurlHttpClient;
use BoudhraaDhia7\Vultr\Http\HttpClientInterface;
use BoudhraaDhia7\Vultr\Http\Response;
use BoudhraaDhia7\Vultr\Http\Transport;
use BoudhraaDhia7\Vultr\Resource\AccountResource;
use BoudhraaDhia7\Vultr\Resource\ApplicationResource;
use BoudhraaDhia7\Vultr\Resource\BackupResource;
use BoudhraaDhia7\Vultr\Resource\BlockStorageResource;
use BoudhraaDhia7\Vultr\Resource\DomainResource;
use BoudhraaDhia7\Vultr\Resource\FirewallResource;
use BoudhraaDhia7\Vultr\Resource\InstanceResource;
use BoudhraaDhia7\Vultr\Resource\IsoResource;
use BoudhraaDhia7\Vultr\Resource\ObjectStorageResource;
use BoudhraaDhia7\Vultr\Resource\OperatingSystemResource;
use BoudhraaDhia7\Vultr\Resource\PlanResource;
use BoudhraaDhia7\Vultr\Resource\RegionResource;
use BoudhraaDhia7\Vultr\Resource\ReservedIpResource;
use BoudhraaDhia7\Vultr\Resource\SnapshotResource;
use BoudhraaDhia7\Vultr\Resource\SshKeyResource;
use BoudhraaDhia7\Vultr\Resource\StartupScriptResource;
use BoudhraaDhia7\Vultr\Resource\UserResource;

/**
 * The entry point to the Vultr API v2.
 *
 *     $vultr = VultrClient::create($_ENV['VULTR_API_KEY']);
 *
 *     foreach ($vultr->instances()->all() as $instance) {
 *         echo $instance['label'], PHP_EOL;
 *     }
 *
 * The client is stateless apart from its configuration, so a single instance
 * can be registered as a singleton in a container and shared freely.
 *
 * Resource objects are created once and reused.
 */
final class VultrClient
{
    /**
     * Kept in step with the git tag; reported in the User-Agent header.
     */
    public const VERSION = '2.0.0';

    private readonly Transport $transport;

    /** @var array<class-string, object> */
    private array $resources = [];

    public function __construct(
        private readonly Config $config,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->transport = new Transport(
            $this->config,
            $httpClient ?? new CurlHttpClient(
                $this->config->connectTimeout(),
                $this->config->timeout(),
                $this->config->caBundle(),
            ),
        );
    }

    /**
     * Build a client from an API key, using the default cURL transport.
     */
    public static function create(string $apiKey, ?string $baseUri = null): self
    {
        return new self(Config::create($apiKey, $baseUri ?? Config::DEFAULT_BASE_URI));
    }

    /**
     * Build a client from the VULTR_API_KEY environment variable.
     */
    public static function fromEnvironment(string $variable = 'VULTR_API_KEY'): self
    {
        return new self(Config::fromEnvironment($variable));
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function account(): AccountResource
    {
        return $this->resource(AccountResource::class);
    }

    public function instances(): InstanceResource
    {
        return $this->resource(InstanceResource::class);
    }

    public function regions(): RegionResource
    {
        return $this->resource(RegionResource::class);
    }

    public function plans(): PlanResource
    {
        return $this->resource(PlanResource::class);
    }

    public function operatingSystems(): OperatingSystemResource
    {
        return $this->resource(OperatingSystemResource::class);
    }

    public function applications(): ApplicationResource
    {
        return $this->resource(ApplicationResource::class);
    }

    public function snapshots(): SnapshotResource
    {
        return $this->resource(SnapshotResource::class);
    }

    public function backups(): BackupResource
    {
        return $this->resource(BackupResource::class);
    }

    public function sshKeys(): SshKeyResource
    {
        return $this->resource(SshKeyResource::class);
    }

    public function startupScripts(): StartupScriptResource
    {
        return $this->resource(StartupScriptResource::class);
    }

    public function reservedIps(): ReservedIpResource
    {
        return $this->resource(ReservedIpResource::class);
    }

    public function isos(): IsoResource
    {
        return $this->resource(IsoResource::class);
    }

    public function blockStorage(): BlockStorageResource
    {
        return $this->resource(BlockStorageResource::class);
    }

    public function domains(): DomainResource
    {
        return $this->resource(DomainResource::class);
    }

    public function objectStorage(): ObjectStorageResource
    {
        return $this->resource(ObjectStorageResource::class);
    }

    public function firewalls(): FirewallResource
    {
        return $this->resource(FirewallResource::class);
    }

    public function users(): UserResource
    {
        return $this->resource(UserResource::class);
    }

    /**
     * Call an endpoint this SDK does not model yet.
     *
     * Authentication, retries, error mapping and JSON decoding still apply.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        return $this->transport->request($method, $path, $query, $body);
    }

    /**
     * As {@see request()}, but hands back the raw response so headers and the
     * status code can be inspected.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null): Response
    {
        return $this->transport->send($method, $path, $query, $body);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function resource(string $class): object
    {
        /** @var T */
        return $this->resources[$class] ??= new $class($this->transport);
    }
}

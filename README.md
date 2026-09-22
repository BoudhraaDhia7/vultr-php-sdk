# Vultr PHP SDK

[![CI](https://github.com/BoudhraaDhia7/vultr-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/BoudhraaDhia7/vultr-php-sdk/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/boudhraadhia7/vultr-php-sdk.svg)](https://packagist.org/packages/boudhraadhia7/vultr-php-sdk)
[![PHP version](https://img.shields.io/packagist/dependency-v/boudhraadhia7/vultr-php-sdk/php.svg)](https://packagist.org/packages/boudhraadhia7/vultr-php-sdk)
[![License](https://img.shields.io/packagist/l/boudhraadhia7/vultr-php-sdk.svg)](LICENSE)

A typed PHP client for the [Vultr API v2](https://www.vultr.com/api/).

It covers instances, DNS, firewalls, block and object storage, snapshots,
backups, SSH keys, startup scripts, reserved IPs, ISOs, users, and the
read-only catalogues (regions, plans, operating systems, applications). Cursor
pagination, retries and error handling are built in.

- **No required dependencies.** PHP 8.2+, `ext-curl` and `ext-json`.
- **Typed errors.** Every failure is an exception, never a magic array.
- **Stateless.** No hidden "current instance"; a single client is safe to share.
- **Injectable transport.** Use the bundled cURL client, or route requests
  through Guzzle, `symfony/http-client` or anything else that speaks PSR-18.
- **Tested.** 124 unit tests, PHPStan level 8, PHP-CS-Fixer, CI on PHP 8.2-8.4.

## Installation

```bash
composer require boudhraadhia7/vultr-php-sdk
```

## Quick start

```php
use BoudhraaDhia7\Vultr\VultrClient;

$vultr = VultrClient::create('YOUR_API_KEY');

// or, keeping the key out of your source:
$vultr = VultrClient::fromEnvironment(); // reads VULTR_API_KEY

foreach ($vultr->instances()->all() as $instance) {
    printf("%s  %s  %s\n", $instance['id'], $instance['label'], $instance['main_ip']);
}
```

Create an API key in the Vultr customer portal under **Account > API**, and add
the IP addresses that will use it to the key's access control list.

## Resources

Each resource group hangs off the client:

| Accessor | Covers |
| --- | --- |
| `$vultr->account()` | Account details, balance, bandwidth |
| `$vultr->instances()` | Instances: lifecycle, addresses, backups, ISO, VPC |
| `$vultr->regions()` | Regions and per-region plan availability |
| `$vultr->plans()` | Cloud compute and bare metal plans |
| `$vultr->operatingSystems()` | Deployable OS images |
| `$vultr->applications()` | One-click and Marketplace applications |
| `$vultr->snapshots()` | Snapshots, including import from a URL |
| `$vultr->backups()` | Automatic backups (read-only) |
| `$vultr->sshKeys()` | SSH public keys |
| `$vultr->startupScripts()` | Boot and PXE scripts |
| `$vultr->reservedIps()` | Reserved (floating) IPs |
| `$vultr->isos()` | Custom and public ISO images |
| `$vultr->blockStorage()` | Block storage volumes |
| `$vultr->domains()` | Managed DNS: zones, records, SOA, DNSSEC |
| `$vultr->objectStorage()` | S3-compatible object storage |
| `$vultr->firewalls()` | Firewall groups and rules |
| `$vultr->users()` | Sub-account users and ACLs |

## Creating an instance

`InstanceBuilder` validates the payload before a request leaves your process,
so a missing plan or two conflicting deployment sources fail immediately rather
than as a 400 from the API.

```php
use BoudhraaDhia7\Vultr\Builder\InstanceBuilder;

$instance = $vultr->instances()->create(
    InstanceBuilder::in('ewr')
        ->plan('vc2-1c-1gb')
        ->fromOperatingSystem(1743)
        ->label('web-01')
        ->hostname('web-01')
        ->tags(['web', 'production'])
        ->sshKeys([$keyId])
        ->enableIpv6()
        ->enableBackups()
        ->userData("#cloud-config\npackages:\n  - nginx\n")
);

echo $instance['id'];
```

Every builder method returns a new builder, so a partially configured one works
as a reusable template:

```php
$template = InstanceBuilder::in('ewr')->plan('vc2-1c-1gb')->fromOperatingSystem(1743);

$web = $vultr->instances()->create($template->label('web-01'));
$api = $vultr->instances()->create($template->label('api-01'));
```

Deploy from a snapshot, ISO, one-click app or Marketplace image with
`fromSnapshot()`, `fromIso()`, `fromApplication()` or `fromMarketplaceApp()`.
Choosing a second source replaces the first rather than sending both.

Anything the builder does not model goes through `set()`:

```php
$template->set('activation_email', false);
```

## Managing instances

Instance ids are always passed explicitly:

```php
$vultr->instances()->reboot($instanceId);
$vultr->instances()->halt($instanceId);
$vultr->instances()->start($instanceId);
$vultr->instances()->setLabel($instanceId, 'web-01-retired');
$vultr->instances()->resize($instanceId, 'vc2-2c-4gb');
$vultr->instances()->delete($instanceId);
```

Backup schedules are enum-typed, and the fields a cadence requires are checked
before the request is sent:

```php
use BoudhraaDhia7\Vultr\Enum\BackupScheduleType;

$vultr->instances()->enableBackups($instanceId);
$vultr->instances()->setBackupSchedule($instanceId, BackupScheduleType::Daily, hour: 3);
$vultr->instances()->setBackupSchedule($instanceId, BackupScheduleType::Weekly, hour: 3, dayOfWeek: 0);
```

## Pagination

Every collection endpoint offers two shapes.

`list()` returns one `Page`, which is countable, iterable and carries the
cursors, for when you are paging in a UI:

```php
$page = $vultr->instances()->list(perPage: 50);

foreach ($page as $instance) { /* ... */ }

$page->total();       // total records, as reported by Vultr
$page->hasMore();     // is there another page
$page->nextCursor();  // pass back as $cursor

$next = $vultr->instances()->list(perPage: 50, cursor: $page->nextCursor());
```

`all()` returns a generator that walks every page for you, fetching lazily:

```php
foreach ($vultr->instances()->all(['region' => 'ewr']) as $instance) {
    // Pages are requested only as you consume them; break out and the
    // remaining pages are never fetched.
}
```

## Error handling

Failures throw. There is nothing to check on the return value.

```php
use BoudhraaDhia7\Vultr\Exception\AuthenticationException;
use BoudhraaDhia7\Vultr\Exception\RateLimitException;
use BoudhraaDhia7\Vultr\Exception\ResourceNotFoundException;
use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\Exception\ValidationException;
use BoudhraaDhia7\Vultr\Exception\VultrExceptionInterface;

try {
    $instance = $vultr->instances()->get($instanceId);
} catch (ResourceNotFoundException) {
    // 404
} catch (ValidationException $e) {
    // 400 or 422
    print_r($e->errorBody());
} catch (AuthenticationException) {
    // 401: key missing, malformed or revoked
} catch (RateLimitException $e) {
    // 429 that survived the built-in retries
    sleep($e->retryAfter() ?? 60);
} catch (TransportException $e) {
    // No HTTP response at all: DNS, TLS, timeout, refused connection
} catch (VultrExceptionInterface $e) {
    // Anything else from this library
}
```

The hierarchy:

```
VultrExceptionInterface
└── VultrException
    ├── ConfigurationException     invalid client or argument configuration
    ├── TransportException         no response was obtained
    └── ApiException               a non-2xx response
        ├── ValidationException        400, 422
        ├── AuthenticationException    401
        ├── AuthorizationException     403
        ├── ResourceNotFoundException  404
        ├── RateLimitException         429
        └── ServerException            5xx
```

Every `ApiException` carries `statusCode()`, `errorBody()`, `request()` and
`response()`. The Authorization header on `request()` is already redacted, so
the exception is safe to log as-is.

## Retries

Rate limits, 5xx responses and transport failures are retried with exponential
backoff and full jitter. A `Retry-After` header is honoured when present, capped
at the configured maximum delay.

Only idempotent methods (`GET`, `HEAD`, `PUT`, `DELETE`) are replayed after a
5xx or a transport failure, so a `POST` that may have already created an
instance is never sent twice. A 429 is replayed for any method, because a
rate-limited request was rejected before it was processed.

```php
use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\VultrClient;

$vultr = new VultrClient(
    Config::create($apiKey)
        ->withMaxRetries(4)                              // default 2; 0 disables
        ->withRetryDelays(baseDelay: 1.0, maxDelay: 30.0)
        ->withTimeout(15.0)
        ->withConnectTimeout(5.0)
);
```

## Configuration

`Config` is immutable: every `with*()` call returns a new instance, so one
configured object can be shared between clients and workers.

| Method | Default | Purpose |
| --- | --- | --- |
| `Config::create($key, $baseUri)` | `https://api.vultr.com/` | Build from an API key |
| `Config::fromEnvironment($var)` | `VULTR_API_KEY` | Read the key from the environment |
| `withTimeout(float)` | `30.0` | Seconds for the whole transfer |
| `withConnectTimeout(float)` | `10.0` | Seconds to establish the connection |
| `withMaxRetries(int)` | `2` | Retry attempts for retryable failures |
| `withRetryDelays(float, float)` | `0.5`, `8.0` | Backoff base and ceiling, in seconds |
| `withDefaultHeaders(array)` | `[]` | Headers added to every request |
| `withCaBundle(string)` | system store | Path to a CA bundle file or directory |

### TLS

Certificate verification is always on and cannot be disabled. If a machine
cannot verify Vultr's certificate, point the client at a valid CA bundle rather
than turning verification off:

```php
$config = Config::create($apiKey)->withCaBundle('/etc/ssl/certs/ca-certificates.crt');
```

## Framework integration

The client is a plain object with no framework coupling. Register it once as a
singleton and type-hint it where you need it.

**Laravel** — in a service provider:

```php
use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\VultrClient;

public function register(): void
{
    $this->app->singleton(
        VultrClient::class,
        fn () => new VultrClient(Config::create((string) config('services.vultr.key'))),
    );
}
```

```php
// config/services.php
'vultr' => ['key' => env('VULTR_API_KEY')],
```

**Symfony** — in `config/services.yaml`:

```yaml
services:
    BoudhraaDhia7\Vultr\Config:
        factory: ['BoudhraaDhia7\Vultr\Config', 'create']
        arguments: ['%env(VULTR_API_KEY)%']

    BoudhraaDhia7\Vultr\VultrClient:
        arguments:
            $config: '@BoudhraaDhia7\Vultr\Config'
```

### Using your own HTTP stack

Pass a `Psr18HttpClient` to share your application's client, middleware and
logging with the SDK. This needs `psr/http-client` and `psr/http-factory`,
which are optional dependencies of this package.

```php
use BoudhraaDhia7\Vultr\Http\Psr18HttpClient;

$vultr = new VultrClient(
    Config::create($apiKey),
    new Psr18HttpClient($guzzle, $requestFactory, $streamFactory),
);
```

To test without the network, implement `HttpClientInterface` yourself; it has a
single `send(Request): Response` method.

## Endpoints not yet modelled

Vultr ships new endpoints regularly. Call them directly and keep the
authentication, retries, error mapping and JSON decoding:

```php
$clusters = $vultr->request('GET', 'v2/kubernetes/clusters', ['per_page' => 25]);

// Or keep the raw response when you need headers or the status code.
$response = $vultr->send('GET', 'v2/account');
$response->statusCode();
$response->rateLimitRemaining();
```

## Examples

Runnable scripts live in [`examples/`](examples):

| File | Shows |
| --- | --- |
| `01-list-instances.php` | Account summary, lazy pagination, byte formatting |
| `02-create-instance.php` | Building and deploying an instance, polling until ready |
| `03-error-handling.php` | The exception hierarchy in practice |
| `04-dns-and-firewall.php` | DNS records and firewall rules with enum parameters |
| `05-framework-integration.php` | Laravel, Symfony and PSR-18 wiring |

## Upgrading from 1.x

Version 2.0 is a rewrite with a new namespace and a new API surface. See
[UPGRADE.md](UPGRADE.md) for a method-by-method mapping and
[CHANGELOG.md](CHANGELOG.md) for the full list of changes, including the
correctness bugs 1.x shipped with.

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan level 8
composer cs        # PHP-CS-Fixer, dry run
composer cs:fix    # PHP-CS-Fixer, apply
composer qa        # all of the above
```

Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).

This package began as a fork of
[cp6/Vultr-API-PHP-class](https://github.com/cp6/Vultr-API-PHP-class) by
corbpie, and has since been rewritten.

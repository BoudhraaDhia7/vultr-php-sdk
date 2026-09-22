# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-09-22

A full rewrite. The single `VultrAPI` god class is replaced by a client with
typed resource objects, exceptions instead of error arrays, and an injectable
transport. See [UPGRADE.md](UPGRADE.md) for the migration path.

### Added

- `VultrClient` entry point with seventeen resource accessors, replacing the
  flat method list on `VultrAPI`.
- Typed exception hierarchy under `VultrExceptionInterface`, mapping HTTP
  statuses to `ValidationException`, `AuthenticationException`,
  `AuthorizationException`, `ResourceNotFoundException`, `RateLimitException`
  and `ServerException`. Each carries the status, decoded error body, request
  and response, with the API key redacted.
- Cursor pagination: `list()` returns a countable, iterable `Page` with
  cursors and totals; `all()` returns a generator that walks every page lazily.
- Automatic retries with exponential backoff and full jitter for rate limits,
  5xx responses and transport failures. `Retry-After` is honoured. Only
  idempotent methods are replayed after a 5xx or transport failure, so a
  `POST` that may have created an instance is never sent twice.
- `InstanceBuilder`, an immutable builder that validates region, plan and
  deployment source before any request is sent, and base64-encodes user data.
- `HttpClientInterface` with two implementations: `CurlHttpClient` (default,
  dependency-free) and `Psr18HttpClient` for Guzzle, `symfony/http-client` or
  any other PSR-18 client.
- Immutable `Config` with timeouts, retry policy, default headers, CA bundle
  and `Config::fromEnvironment()`.
- Enums for backup schedules, DNS record types, reserved IP families and
  deployment sources.
- `FirewallResource` covering firewall groups and rules, and VPC attach/detach
  on instances. Neither existed in 1.x.
- `VultrClient::request()` and `VultrClient::send()` as escape hatches for
  endpoints the SDK does not model yet.
- `Util\Bytes` for converting and formatting the raw byte counts the bandwidth
  endpoints return.
- 124 unit tests, PHPStan level 8, PHP-CS-Fixer, and GitHub Actions CI across
  PHP 8.2, 8.3 and 8.4.

### Fixed

Bugs that were present in 1.0.1:

- `requires_sub_id` was set to `true` by any instance method and never reset,
  so every later call on the same object demanded an instance id, including
  calls that had nothing to do with instances. State now lives in the method
  arguments; the client holds none.
- `accountRemainingCredit()` and `osName()` called `json_decode()` on a value
  that `doCall()` had already decoded into an array, so both raised a type
  error on every invocation.
- `serverCreateHostname()`, `serverCreateWithIpv4()`, `serverCreateEnableIpv6()`,
  `serverCreateEnablePrivateNetwork()`, `serverCreateStartScript()`,
  `serverCreateIPXEURL()`, `serverEnableBackups()` and
  `serverCreateEnableDDOSProtection()` wrote their payload keys with a trailing
  space (`'hostname '`), so Vultr silently ignored every one of those options.
- `serverCreateDC()` assigned to the builder array instead of merging into it,
  discarding anything set before it.
- `instanceCreateIpv4()` and `instanceDestroyIpv4()` requested `v2/instance/...`
  instead of `v2/instances/...` and always returned 404.
- `deleteUser()` appended a trailing space to the request path.
- `destroyStartupScript()` and `updateStartupScript()` posted to
  `v2/startup-scripts/destroy` and `/update`, which are not Vultr endpoints.
- `createSnapshotFromURL()` used the singular `v2/snapshot/create-from-url`.
- `dnsCreateDomain()` sent `serverip` and a boolean `dns_sec`; the API expects
  `ip` and the strings `enabled` or `disabled`.
- `dnsCreateRecord()` posted to `v2/domains/{domain}/record` (singular) and
  duplicated the domain in the body.
- `dnsDeleteDomain()` sent `DELETE v2/domains` with the domain in the body
  instead of `DELETE v2/domains/{domain}`.
- Enabling backups sent `enable`/`disable`; the API expects `enabled`/`disabled`.
- `convertBytes()` ran its result through `number_format()` before casting back
  to float, so any value above 999 was truncated at the thousands separator:
  one gibibyte came back from a megabyte conversion as `1.0` rather than
  `1024.0`.
- Path segments were interpolated without encoding, so an id containing a
  slash or a space produced a malformed URL.
- `doCall()` accepted a `$headers` argument that it overwrote and never used;
  every call site passed one regardless.

### Changed

- **Namespace** is now `BoudhraaDhia7\Vultr\`, was
  `BoudhraaDhia7\VultrLaravelSymfony\`.
- Minimum PHP is 8.2. `ext-json` is now an explicit requirement.
- Errors throw instead of returning an array with an `ok` key, so a failure can
  no longer be mistaken for a result.
- `doCall()`'s `$return_http_code` flag is gone. Methods return decoded data,
  or nothing for endpoints that answer 204.
- Instance ids are method arguments rather than client state; `setSubId()` is
  gone.
- Responses are returned decoded. 1.x returned an array from `doCall()` while
  its own helpers and README treated the result as a JSON string.
- `LICENSE` is now the MIT license that `composer.json` has always declared.
  1.x shipped a GPL-3.0 file inherited from upstream, contradicting its own
  metadata.

### Removed

- **TLS verification can no longer be disabled.** The `$useTLS` constructor
  flag is gone, along with the README section recommending it for local
  development. Use `Config::withCaBundle()` to point at a valid CA bundle
  instead.
- `serverCreateOptions()`, which echoed HTML directly from the library.
- The `$debug` flag, which switched error arrays between shapes and echoed the
  request headers, including the API key, into the response. Exceptions now
  always carry the full context, with the key redacted.
- `boolToInt()`, which nothing used.

## [1.0.1] - 2025-09-03

- Fixed instance id handling and added a debug mode.

## [1.0.0] - 2025-09-03

- First release of the fork, based on
  [cp6/Vultr-API-PHP-class](https://github.com/cp6/Vultr-API-PHP-class).

[2.0.0]: https://github.com/BoudhraaDhia7/vultr-php-sdk/compare/v1.0.1...v2.0.0
[1.0.1]: https://github.com/BoudhraaDhia7/vultr-php-sdk/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/BoudhraaDhia7/vultr-php-sdk/releases/tag/v1.0.0

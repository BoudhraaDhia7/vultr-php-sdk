# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 2.x | Yes |
| 1.x | Security fixes only |

## Reporting a vulnerability

Do not open a public issue for a security problem.

Report it through GitHub's private vulnerability reporting on this repository
(**Security > Report a vulnerability**), or by email to
[dhia.b@externalisation.eu](mailto:dhia.b@externalisation.eu).

Please include the affected version, a description of the issue, and the steps
to reproduce it. You can expect an acknowledgement within a few days, and an
assessment of the report shortly after.

## Handling credentials

A few notes on how this library treats your API key, so you know what is safe
to log:

- The key is held in `Config` and sent only as an `Authorization: Bearer`
  header to the configured base URI.
- Exceptions expose the failing request through `ApiException::request()` with
  the Authorization header replaced by `Bearer [redacted]`, so the exception is
  safe to log as-is. Request bodies are not redacted and may contain passwords
  you passed to `users()->create()`.
- The key is never written to disk, and no telemetry is sent anywhere.
- TLS certificate verification is always enabled and cannot be turned off. Use
  `Config::withCaBundle()` if a host has an incomplete trust store.

Prefer `VultrClient::fromEnvironment()` over hardcoding the key, and restrict
each key to the IP addresses that use it under **Account > API** in the Vultr
customer portal.

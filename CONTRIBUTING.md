# Contributing

Thanks for taking the time to contribute.

## Getting set up

```bash
git clone https://github.com/BoudhraaDhia7/vultr-php-sdk.git
cd vultr-php-sdk
composer install
```

You need PHP 8.2 or newer with `ext-curl` and `ext-json`.

## Before opening a pull request

Run the full check locally. CI runs the same three gates, so a green run here
means a green run there.

```bash
composer qa
```

That is:

| Command | Gate |
| --- | --- |
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan, level 8, no baseline |
| `composer cs` | PHP-CS-Fixer, dry run |

`composer cs:fix` applies the formatting rather than just reporting it.

## Writing code

- Every file declares `strict_types=1`.
- Public methods carry a return type and, where an array is involved, an array
  shape in the docblock. PHPStan runs at level 8 with no baseline; keep it that
  way rather than adding ignores.
- Resource classes hold no state. An id belongs in the method arguments.
- New API coverage goes in a `*Resource` class under `src/Resource/`, is
  exposed through an accessor on `VultrClient`, and gets a test that asserts
  the method, path and body the SDK produces.

## Writing tests

Tests never touch the network. Extend `Tests\Support\TestCase`, queue responses
on `$this->http`, and assert on what was sent:

```php
public function testItSetsTheLabel(): void
{
    $this->http->queueJson(['instance' => ['id' => 'abc']]);

    $this->client()->instances()->setLabel('abc', 'web-01');

    $this->assertRequestMethod('PATCH');
    $this->assertRequestPath('v2/instances/abc');
    self::assertSame(['label' => 'web-01'], $this->http->bodyAt(0));
}
```

`MockHttpClient` fails the test if a request arrives with an empty response
queue, so an unexpected extra call is caught rather than silently passing.

## Reporting a bug

Include the SDK version, the PHP version, the call you made, and the exception
message. Redact the API key: exception messages already have the Authorization
header masked, but request bodies and URLs may still carry identifiers you
would rather not publish.

## Commit messages

Write a short imperative subject line describing the change. Explain the why in
the body when it is not obvious from the diff.

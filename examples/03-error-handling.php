<?php

declare(strict_types=1);

/*
 * Shows how failures surface: every error is a typed exception, so there is no
 * need to inspect return values for magic error keys.
 *
 * Run with:
 *     VULTR_API_KEY=your-key php examples/03-error-handling.php
 */

use BoudhraaDhia7\Vultr\Exception\AuthenticationException;
use BoudhraaDhia7\Vultr\Exception\RateLimitException;
use BoudhraaDhia7\Vultr\Exception\ResourceNotFoundException;
use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\Exception\ValidationException;
use BoudhraaDhia7\Vultr\Exception\VultrExceptionInterface;
use BoudhraaDhia7\Vultr\VultrClient;

require __DIR__.'/../vendor/autoload.php';

$vultr = VultrClient::fromEnvironment();

try {
    $vultr->instances()->get('00000000-0000-0000-0000-000000000000');
} catch (ResourceNotFoundException $exception) {
    // The most specific case first.
    printf('Not found: %s%s', $exception->getMessage(), PHP_EOL);
} catch (AuthenticationException $exception) {
    printf('The API key was rejected: %s%s', $exception->getMessage(), PHP_EOL);
} catch (ValidationException $exception) {
    printf('Vultr rejected the request: %s%s', $exception->getMessage(), PHP_EOL);
    print_r($exception->errorBody());
} catch (RateLimitException $exception) {
    // Retries already happened; this means the limit held across all of them.
    printf('Rate limited, retry after %d seconds%s', $exception->retryAfter() ?? 60, PHP_EOL);
} catch (TransportException $exception) {
    // No HTTP response at all: DNS, TLS, timeout, refused connection.
    printf('Could not reach the API: %s%s', $exception->getMessage(), PHP_EOL);
} catch (VultrExceptionInterface $exception) {
    // Anything else this library throws.
    printf('Unexpected SDK failure: %s%s', $exception->getMessage(), PHP_EOL);
}

// The exception carries the full context, with the API key already redacted,
// so it is safe to log.
try {
    $vultr->instances()->create(['region' => 'nowhere', 'plan' => 'nope', 'os_id' => 1]);
} catch (ValidationException $exception) {
    printf(
        '%s %s -> HTTP %d%s',
        $exception->request()->method(),
        $exception->request()->uri(),
        $exception->statusCode(),
        PHP_EOL,
    );
    printf('Authorization header in logs: %s%s', $exception->request()->header('Authorization'), PHP_EOL);
}

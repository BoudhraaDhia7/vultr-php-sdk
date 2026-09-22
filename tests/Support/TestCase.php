<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Support;

use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\VultrClient;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Wires a VultrClient to a MockHttpClient so test cases can assert on the
 * requests the SDK produces.
 */
abstract class TestCase extends BaseTestCase
{
    protected MockHttpClient $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new MockHttpClient();
    }

    protected function client(?Config $config = null): VultrClient
    {
        return new VultrClient($config ?? $this->config(), $this->http);
    }

    protected function config(): Config
    {
        // Retries are off by default in tests so a queued failure surfaces
        // immediately instead of consuming extra queued responses.
        return Config::create('test-api-key')->withMaxRetries(0);
    }

    /**
     * Assert the path and query string of a recorded request, ignoring the host.
     */
    protected function assertRequestPath(string $expected, int $index = 0): void
    {
        $uri = $this->http->requestAt($index)->uri();

        self::assertSame(
            $expected,
            substr($uri, strlen(Config::DEFAULT_BASE_URI)),
            sprintf('Unexpected path for request %d.', $index),
        );
    }

    protected function assertRequestMethod(string $expected, int $index = 0): void
    {
        self::assertSame($expected, $this->http->requestAt($index)->method());
    }
}

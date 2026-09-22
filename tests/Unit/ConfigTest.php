<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testItRejectsAnEmptyApiKey(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('A Vultr API key is required');

        Config::create('   ');
    }

    public function testItTrimsTheApiKey(): void
    {
        self::assertSame('abc123', Config::create("  abc123\n")->apiKey());
    }

    public function testItNormalisesTheBaseUri(): void
    {
        self::assertSame(
            'https://api.vultr.com/',
            Config::create('k', 'https://api.vultr.com')->baseUri(),
        );

        self::assertSame(
            'https://api.vultr.com/',
            Config::create('k', 'https://api.vultr.com///')->baseUri(),
        );
    }

    #[DataProvider('invalidBaseUris')]
    public function testItRejectsANonAbsoluteBaseUri(string $baseUri): void
    {
        $this->expectException(ConfigurationException::class);

        Config::create('k', $baseUri);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseUris(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['/v2'];
        yield 'no scheme' => ['api.vultr.com'];
        yield 'wrong scheme' => ['ftp://api.vultr.com'];
    }

    public function testMutatorsReturnANewInstanceAndLeaveTheOriginalAlone(): void
    {
        $original = Config::create('k');
        $changed = $original->withTimeout(5.0);

        self::assertNotSame($original, $changed);
        self::assertSame(30.0, $original->timeout());
        self::assertSame(5.0, $changed->timeout());
        self::assertSame('k', $changed->apiKey());
    }

    public function testItRejectsNonPositiveTimeouts(): void
    {
        $this->expectException(ConfigurationException::class);

        Config::create('k')->withTimeout(0.0);
    }

    public function testItRejectsANegativeRetryCount(): void
    {
        $this->expectException(ConfigurationException::class);

        Config::create('k')->withMaxRetries(-1);
    }

    public function testItRejectsAMaximumRetryDelayBelowTheBaseDelay(): void
    {
        $this->expectException(ConfigurationException::class);

        Config::create('k')->withRetryDelays(5.0, 1.0);
    }

    public function testItRejectsAMissingCaBundle(): void
    {
        $this->expectException(ConfigurationException::class);

        Config::create('k')->withCaBundle('/definitely/not/a/real/bundle.pem');
    }

    public function testItReadsTheApiKeyFromTheEnvironment(): void
    {
        putenv('VULTR_TEST_KEY=from-env');

        try {
            self::assertSame('from-env', Config::fromEnvironment('VULTR_TEST_KEY')->apiKey());
        } finally {
            putenv('VULTR_TEST_KEY');
        }
    }

    public function testItFailsWhenTheEnvironmentVariableIsMissing(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('VULTR_ABSENT_KEY');

        Config::fromEnvironment('VULTR_ABSENT_KEY');
    }
}

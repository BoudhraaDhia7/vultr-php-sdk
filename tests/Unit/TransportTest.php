<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\Exception\AuthenticationException;
use BoudhraaDhia7\Vultr\Exception\RateLimitException;
use BoudhraaDhia7\Vultr\Exception\ResourceNotFoundException;
use BoudhraaDhia7\Vultr\Exception\ServerException;
use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\Exception\ValidationException;
use BoudhraaDhia7\Vultr\Http\Response;
use BoudhraaDhia7\Vultr\Http\Transport;
use BoudhraaDhia7\Vultr\Tests\Support\MockHttpClient;
use BoudhraaDhia7\Vultr\VultrClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(Transport::class)]
final class TransportTest extends TestCase
{
    private MockHttpClient $http;

    /** @var list<float> */
    private array $slept = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new MockHttpClient();
        $this->slept = [];
    }

    public function testItSendsTheBearerTokenAndJsonHeaders(): void
    {
        $this->http->queueJson(['account' => []]);

        $this->transport()->request('GET', 'v2/account');

        $request = $this->http->lastRequest();

        self::assertSame('Bearer test-api-key', $request->header('Authorization'));
        self::assertSame('application/json', $request->header('Accept'));
        self::assertStringStartsWith('vultr-php-sdk/'.VultrClient::VERSION, (string) $request->header('User-Agent'));
    }

    public function testItSetsAContentTypeOnlyWhenThereIsABody(): void
    {
        $this->http->queueJson([])->queueJson([]);

        $transport = $this->transport();
        $transport->request('GET', 'v2/account');
        self::assertNull($this->http->lastRequest()->header('Content-Type'));

        $transport->request('POST', 'v2/instances', [], ['region' => 'ewr']);
        self::assertSame('application/json', $this->http->lastRequest()->header('Content-Type'));
        self::assertSame('{"region":"ewr"}', $this->http->lastRequest()->body());
    }

    public function testItBuildsQueryStringsWithBooleansAndLists(): void
    {
        $this->http->queueJson([]);

        $this->transport()->request('GET', 'v2/instances', [
            'per_page' => 100,
            'enabled' => true,
            'disabled' => false,
            'skipped' => null,
            'tag' => ['web', 'prod'],
        ]);

        self::assertSame(
            'https://api.vultr.com/v2/instances?per_page=100&enabled=true&disabled=false&tag=web&tag=prod',
            $this->http->lastRequest()->uri(),
        );
    }

    public function testItEncodesQueryValuesSafely(): void
    {
        $this->http->queueJson([]);

        $this->transport()->request('GET', 'v2/instances', ['label' => 'web & api']);

        self::assertStringEndsWith('?label=web%20%26%20api', $this->http->lastRequest()->uri());
    }

    public function testItJoinsTheBaseUriAndPathWithoutDoublingSlashes(): void
    {
        $this->http->queueJson([]);

        $this->transport()->request('GET', '/v2/account');

        self::assertSame('https://api.vultr.com/v2/account', $this->http->lastRequest()->uri());
    }

    /**
     * @param class-string<Throwable> $expected
     */
    #[DataProvider('errorStatuses')]
    public function testItMapsStatusCodesToExceptions(int $status, string $expected): void
    {
        $this->http->queueJson(['error' => 'boom', 'status' => $status], $status);

        $this->expectException($expected);
        $this->expectExceptionMessage('boom');

        $this->transport()->request('GET', 'v2/account');
    }

    /**
     * @return iterable<string, array{int, class-string}>
     */
    public static function errorStatuses(): iterable
    {
        yield '400' => [400, ValidationException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '404' => [404, ResourceNotFoundException::class];
        yield '422' => [422, ValidationException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
    }

    public function testItRedactsTheApiKeyOnTheExceptionRequest(): void
    {
        $this->http->queueJson(['error' => 'nope'], 401);

        try {
            $this->transport()->request('GET', 'v2/account');
            self::fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $exception) {
            self::assertSame('Bearer [redacted]', $exception->request()->header('Authorization'));
            self::assertSame(401, $exception->statusCode());
            self::assertSame(['error' => 'nope'], $exception->errorBody());
        }
    }

    public function testItRetriesRateLimitedRequestsAndReturnsTheSuccess(): void
    {
        $this->http
            ->queueJson(['error' => 'rate limited'], 429)
            ->queueJson(['account' => ['name' => 'ok']]);

        $result = $this->transport(Config::create('test-api-key')->withMaxRetries(2))
            ->request('GET', 'v2/account');

        self::assertSame(['account' => ['name' => 'ok']], $result);
        self::assertSame(2, $this->http->requestCount());
        self::assertCount(1, $this->slept);
    }

    public function testItHonoursTheRetryAfterHeader(): void
    {
        $this->http
            ->queueResponse(new Response(429, ['retry-after' => '3'], '{"error":"slow down"}'))
            ->queueJson(['account' => []]);

        $this->transport(Config::create('test-api-key')->withMaxRetries(1))
            ->request('GET', 'v2/account');

        self::assertSame([3.0], $this->slept);
    }

    public function testItCapsTheRetryAfterDelayAtTheConfiguredMaximum(): void
    {
        $this->http
            ->queueResponse(new Response(429, ['retry-after' => '600'], '{}'))
            ->queueJson([]);

        $this->transport(
            Config::create('test-api-key')->withMaxRetries(1)->withRetryDelays(0.5, 4.0),
        )->request('GET', 'v2/account');

        self::assertSame([4.0], $this->slept);
    }

    public function testItGivesUpAfterTheConfiguredNumberOfRetries(): void
    {
        $this->http
            ->queueJson(['error' => 'rate limited'], 429)
            ->queueJson(['error' => 'rate limited'], 429)
            ->queueJson(['error' => 'rate limited'], 429);

        $this->expectException(RateLimitException::class);

        try {
            $this->transport(Config::create('test-api-key')->withMaxRetries(2))
                ->request('GET', 'v2/account');
        } finally {
            self::assertSame(3, $this->http->requestCount());
        }
    }

    public function testItRetriesServerErrorsOnIdempotentMethods(): void
    {
        $this->http
            ->queueJson(['error' => 'oops'], 503)
            ->queueJson(['regions' => []]);

        $this->transport(Config::create('test-api-key')->withMaxRetries(1))
            ->request('GET', 'v2/regions');

        self::assertSame(2, $this->http->requestCount());
    }

    public function testItDoesNotRetryServerErrorsOnPostSoInstancesAreNotCreatedTwice(): void
    {
        $this->http->queueJson(['error' => 'oops'], 500);

        $this->expectException(ServerException::class);

        try {
            $this->transport(Config::create('test-api-key')->withMaxRetries(3))
                ->request('POST', 'v2/instances', [], ['region' => 'ewr']);
        } finally {
            self::assertSame(1, $this->http->requestCount());
        }
    }

    public function testItRetriesRateLimitedPostsBecauseTheyWereNeverProcessed(): void
    {
        $this->http
            ->queueJson(['error' => 'rate limited'], 429)
            ->queueJson(['instance' => ['id' => 'abc']]);

        $this->transport(Config::create('test-api-key')->withMaxRetries(1))
            ->request('POST', 'v2/instances', [], ['region' => 'ewr']);

        self::assertSame(2, $this->http->requestCount());
    }

    public function testItRetriesTransportFailuresOnIdempotentMethods(): void
    {
        $this->http
            ->queueTransportFailure()
            ->queueJson(['account' => []]);

        $this->transport(Config::create('test-api-key')->withMaxRetries(1))
            ->request('GET', 'v2/account');

        self::assertSame(2, $this->http->requestCount());
    }

    public function testItDoesNotRetryTransportFailuresOnPost(): void
    {
        $this->http->queueTransportFailure('connection refused');

        $this->expectException(TransportException::class);

        try {
            $this->transport(Config::create('test-api-key')->withMaxRetries(3))
                ->request('POST', 'v2/instances', [], []);
        } finally {
            self::assertSame(1, $this->http->requestCount());
        }
    }

    public function testItTreatsAnEmptyBodyAsAnEmptyArray(): void
    {
        $this->http->queueNoContent();

        self::assertSame([], $this->transport()->request('DELETE', 'v2/instances/abc'));
    }

    public function testItFailsLoudlyOnANonJsonSuccessBody(): void
    {
        $this->http->queueResponse(new Response(200, [], '<html>maintenance</html>'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Expected a JSON object');

        $this->transport()->request('GET', 'v2/account');
    }

    public function testItFallsBackToTheRawBodyWhenAnErrorIsNotJson(): void
    {
        $this->http->queueResponse(new Response(502, [], 'Bad Gateway'));

        try {
            $this->transport()->request('GET', 'v2/account');
            self::fail('Expected a ServerException.');
        } catch (ServerException $exception) {
            self::assertStringContainsString('HTTP 502', $exception->getMessage());
            self::assertStringContainsString('Bad Gateway', $exception->getMessage());
        }
    }

    public function testItAppliesDefaultHeaders(): void
    {
        $this->http->queueJson([]);

        $config = Config::create('test-api-key')
            ->withMaxRetries(0)
            ->withDefaultHeaders(['X-Request-Id' => 'abc-123']);

        $this->transport($config)->request('GET', 'v2/account');

        self::assertSame('abc-123', $this->http->lastRequest()->header('X-Request-Id'));
    }

    private function transport(?Config $config = null): Transport
    {
        return new Transport(
            $config ?? Config::create('test-api-key')->withMaxRetries(0),
            $this->http,
            function (float $seconds): void {
                $this->slept[] = $seconds;
            },
        );
    }
}

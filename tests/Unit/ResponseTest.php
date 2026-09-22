<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\Http\Request;
use BoudhraaDhia7\Vultr\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
#[CoversClass(Request::class)]
final class ResponseTest extends TestCase
{
    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '{}');

        self::assertSame('application/json', $response->header('content-type'));
        self::assertSame('application/json', $response->header('CONTENT-TYPE'));
        self::assertNull($response->header('x-missing'));
    }

    public function testAnEmptyBodyDecodesToAnEmptyArray(): void
    {
        self::assertSame([], (new Response(204))->json());
        self::assertNull((new Response(204))->jsonOrNull());
    }

    public function testInvalidJsonThrowsFromJsonButNotFromJsonOrNull(): void
    {
        $response = new Response(200, [], 'not json');

        self::assertNull($response->jsonOrNull());

        $this->expectException(TransportException::class);
        $response->json();
    }

    public function testAJsonScalarIsNotAcceptedAsAnObject(): void
    {
        self::assertNull((new Response(200, [], '"hello"'))->jsonOrNull());
    }

    #[DataProvider('statuses')]
    public function testSuccessDetection(int $status, bool $expected): void
    {
        self::assertSame($expected, (new Response($status))->isSuccessful());
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function statuses(): iterable
    {
        yield '200' => [200, true];
        yield '201' => [201, true];
        yield '204' => [204, true];
        yield '299' => [299, true];
        yield '300' => [300, false];
        yield '400' => [400, false];
        yield '500' => [500, false];
    }

    public function testRateLimitRemainingIsParsed(): void
    {
        self::assertSame(12, (new Response(200, ['x-ratelimit-remaining' => '12']))->rateLimitRemaining());
        self::assertNull((new Response(200))->rateLimitRemaining());
    }

    public function testRequestRedactsTheAuthorizationHeader(): void
    {
        $request = new Request('GET', 'https://api.vultr.com/v2/account', [
            'Authorization' => 'Bearer super-secret',
            'Accept' => 'application/json',
        ]);

        $redacted = $request->withRedactedCredentials();

        self::assertSame('Bearer [redacted]', $redacted->header('Authorization'));
        self::assertSame('application/json', $redacted->header('Accept'));
        self::assertSame('Bearer super-secret', $request->header('Authorization'));
    }

    public function testRequestHeaderLinesAreFormattedForCurl(): void
    {
        $request = new Request('GET', 'https://api.vultr.com/', ['Accept' => 'application/json']);

        self::assertSame(['Accept: application/json'], $request->headerLines());
    }

    #[DataProvider('methods')]
    public function testIdempotencyDetection(string $method, bool $expected): void
    {
        self::assertSame($expected, (new Request($method, 'https://api.vultr.com/'))->isIdempotent());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function methods(): iterable
    {
        yield 'GET' => ['GET', true];
        yield 'HEAD' => ['HEAD', true];
        yield 'PUT' => ['PUT', true];
        yield 'DELETE' => ['DELETE', true];
        yield 'lowercase get' => ['get', true];
        yield 'POST' => ['POST', false];
        yield 'PATCH' => ['PATCH', false];
    }
}

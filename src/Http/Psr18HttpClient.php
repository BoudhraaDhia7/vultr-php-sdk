<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Http;

use BoudhraaDhia7\Vultr\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Routes SDK requests through any PSR-18 client.
 *
 * Use this to share your application's HTTP stack, middleware and logging with
 * the SDK, for example Guzzle in Laravel or symfony/http-client in Symfony.
 *
 * Requires psr/http-client and psr/http-factory, which are optional
 * dependencies of this package.
 */
final class Psr18HttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(Request $request): Response
    {
        $psrRequest = $this->requestFactory->createRequest($request->method(), $request->uri());

        foreach ($request->headers() as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        $body = $request->body();

        if (null !== $body) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($body));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(sprintf(
                '%s %s failed at the transport level: %s',
                $request->method(),
                $request->uri(),
                $exception->getMessage(),
            ), 0, $exception);
        }

        $headers = [];

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }

        return new Response(
            $psrResponse->getStatusCode(),
            $headers,
            (string) $psrResponse->getBody(),
        );
    }
}

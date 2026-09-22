<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Support;

use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\Http\HttpClientInterface;
use BoudhraaDhia7\Vultr\Http\Request;
use BoudhraaDhia7\Vultr\Http\Response;
use PHPUnit\Framework\Assert;

/**
 * A transport that replays a queue of prepared responses and records every
 * request it was given, so tests never touch the network.
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<Response|TransportException> */
    private array $queue = [];

    /** @var list<Request> */
    private array $requests = [];

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    public function queueJson(array $body, int $status = 200, array $headers = []): self
    {
        return $this->queueResponse(new Response(
            $status,
            ['content-type' => 'application/json'] + $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    public function queueNoContent(): self
    {
        return $this->queueResponse(new Response(204));
    }

    public function queueResponse(Response $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    public function queueTransportFailure(string $message = 'connection refused'): self
    {
        $this->queue[] = new TransportException($message);

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        if ([] === $this->queue) {
            Assert::fail(sprintf(
                'MockHttpClient received an unexpected %s %s: the response queue is empty.',
                $request->method(),
                $request->uri(),
            ));
        }

        $next = array_shift($this->queue);

        if ($next instanceof TransportException) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return list<Request>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }

    public function lastRequest(): Request
    {
        $last = end($this->requests);

        if (!$last instanceof Request) {
            Assert::fail('No request was sent.');
        }

        return $last;
    }

    public function requestAt(int $index): Request
    {
        if (!isset($this->requests[$index])) {
            Assert::fail(sprintf('No request was recorded at index %d.', $index));
        }

        return $this->requests[$index];
    }

    /**
     * The decoded JSON body of a recorded request.
     *
     * @return array<string, mixed>
     */
    public function bodyAt(int $index): array
    {
        $body = $this->requestAt($index)->body();

        if (null === $body) {
            return [];
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    public function isDrained(): bool
    {
        return [] === $this->queue;
    }
}
